<?php
global $wpdb;
global $current_role;
global $blog_id;
global $ci_all_btypes;
global $access_btypes;
global $is_ci_admin;




$user_id        = get_current_user_id();
$user			= wp_get_current_user();
$settotal       = false;
$has_quickedit  = false;
$mv_bref_top      = tr_get_option('ci_pdf_data', 'mv_bref_top');
$inb_rv_mp      = tr_get_option('ci_pdf_data', 'inb_rv_mp');
$inb_ccbdob     = tr_get_option('ci_pdf_data', 'inb_ccbdob');
$inb_ssn        = tr_get_option('ci_pdf_data', 'inb_ssn');
$inb_insmid     = tr_get_option('ci_pdf_data', 'inb_insmid');
$inb_insgid     = tr_get_option('ci_pdf_data', 'inb_insgid');
$show_paid      = tr_get_option('ci_pdf_data', 'inb_paid');
$show_stat      = tr_get_option('ci_pdf_data', 'enable_stat');
$inb_disdeletelids = tr_get_option('ci_pdf_data', 'inb_disdeletelids', array());
$hide_col_medication = tr_get_option('ci_pdf_data', 'hide_ib_medication');
if (!in_array('tox', $ci_all_btypes)) {
	$hide_col_medication = true;
}


$inb_disdeletelids = is_array($inb_disdeletelids) ? $inb_disdeletelids : array();

$list_tab_show_qc = array('batch000', 'batches', 'current_batches');

if (tr_get_option('ci_pdf_data', 'inb_hqcinb')) {
	unset($list_tab_show_qc[0]);
}

$btype_colors = array(
	'tox' => 'black',
	'blood' => 'red',
	'dna' => 'blue',
	//'ngs' => 'green',
	'pcr' => '#de7789',
	'study' => '#ff00ff',
	'gene' => '#adadad',
	'req' => '#232323'
);

$editable       = false;
$can_delete     = false;
$can_delete_bulk = false;
$ref_lab_ID     = 0;

$batch_id       = isset($_REQUEST['batch_id']) ? $_REQUEST['batch_id'] : '';
$allstatus      = isset($_REQUEST['allstatus']);
$status         = isset($_REQUEST['status']) ? $_REQUEST['status'] : 'all';
$btype_selected = isset($_REQUEST['qbtype']) ? $_REQUEST['qbtype'] : '';
$city_selected  = isset($_REQUEST['city']) ? $_REQUEST['city'] : '';
$doc_selected   = isset($_REQUEST['doc']) ? $_REQUEST['doc'] : '';
$lab_selected   = isset($_REQUEST['lid']) ? $_REQUEST['lid'] : '';
$clinic_selected = isset($_GET['cid']) ? $_GET['cid'] : 0;
$row_per_page   = 100;

if (empty($batch_id) && empty($_REQUEST['s']) && empty($city_selected) && empty($doc_selected) && empty($clinic_selected)) {
	$batch_id = tr_pdd_get_current_batch_id();
}

if (isset($_REQUEST['act']) && $_REQUEST['act'] == 'change_qc') {
	$return = array('status' => 'ok', 'msg' => 'Updated');
	$table  = 'ci_batches';
	$mid    = $_REQUEST['mid'];
	$btype  = 'tox';
	$ctype  = isset($_REQUEST['ctype']) ? $_REQUEST['ctype'] : 'qc';
	$checked = $_REQUEST['checked'] ? 1 : 0;
	if (stripos($mid, '_') !== false) {
		list($btype, $mid) = explode('_', $mid);
		$table = 'ci_batches_' . $btype;
	}
	$wpdb->update($table, array($ctype => $checked), array('mid' => $mid));

	wp_send_json($return);
} else if ($act == 'move_to_archived') {
	$return = array('success' => '1', 'msg' => 'Moved Successfully');
	$items = $_POST['items'];
	foreach ($items as $mid) {
		list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);
		$wpdb->update($table, array('batch_status' => 'archived'), array('mid' => $mid));
		$assen = $wpdb->get_var("select assen from {$table} where mid = {$mid} ");
		trpdd_log_user(get_current_user_id(), 'archived', '', $mid, $btype, '', '', $assen);
	}
	wp_send_json($return);
} else if ($act == 'delete_selected') {
	$return = array('success' => '1', 'msg' => 'Deleted Successfully');
	$items = $_POST['items'];
	foreach ($items as $mid) {
		list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);
		trpdd_delete_batch($mid, $btype);
	}
	wp_send_json($return);
} else if ($act == 'move_to_reflab') {
	$return = array('success' => '1', 'msg' => 'Moved Successfully');
	$ref_lab_ID = $_REQUEST['ref_lab_id'];

	//check sync soap
	$syncref = $wpdb->get_row("select * from ci_wsdl_keys where wsdl_active = 1 
                        and wsdl_type = 'getreqrec'
                         and wsdl_ref_lab_ID = {$ref_lab_ID}");
	$client = false;
	if ($syncref) {
		try {
			$client = new CI_Data_Work_Client($syncref);
			$client->default_cli_ID = $syncref->wsdl_df_cli_ID;
			$client->default_doc_ID = $syncref->wsdl_df_doc_ID;
		} catch (Exception $e) {
		}
	}

	$items = $_POST['items'];
	foreach ($items as $mid) {
		list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);
		$bat = $wpdb->get_row("select mid, assen, patient_id, cli_ID from {$table} where mid = {$mid} ");
		$update_bat = array('ref_lab_ID' => $ref_lab_ID);
		$reflabdb = $wpdb->get_row("select * from ci_labs where lab_ID = {$ref_lab_ID} ");
		if ($reflabdb && $reflabdb->lab_ref_assen_init && strlen($bat->assen) > 8) {
			$subassen = preg_replace("/^[a-zA-Z]{3}/", '', $bat->assen);
			if (empty($subassen)) {
				$subassen = substr($bat->assen, 3);
			}
			$update_bat['assen'] = $reflabdb->lab_dba_init . $subassen;
		}
		$wpdb->update($table, $update_bat, array('mid' => $mid));

		trpdd_log_user(0, 'move_ref', $ref_lab_ID, $mid, $btype, '', '', $bat->assen);

		if ($syncref && $bat && (empty($bat->patient_id) || empty($bat->doc))) {
			try {
				$rs = $client->sync_req($bat->assen, $btype);

				//$return['rs'] = $rs;
			} catch (Exception $e) {
			}
		}
	}

	wp_send_json($return);
} else if ($act == 'remove_reflab') {
	$return = array('success' => '1', 'msg' => 'Moved Successfully');
	$items = $_POST['items'];
	foreach ($items as $mid) {
		list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);
		$wpdb->update($table, array('ref_lab_ID' => 0), array('mid' => $mid));
		$assen = $wpdb->get_var("select assen from {$table} where mid = {$mid} ");

		trpdd_log_user(0, 'remove_ref', '', $mid, $btype, '', '', $assen);
	}
	wp_send_json($return);
}



if (!empty($lab_selected)) {
	if (stripos($lab_selected, 'ref_') !== false) {
		$ref_lab_ID = str_replace('ref_', '', $lab_selected);
	}
}
$mf_fa_groups = array();
if (tr_get_option('ci_pdf_data', 'inb_showmf_fa')) {
	$mf_fa_groups['mf'] = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type = 'blood'
                        and tg_name like '%Mechanical Fragility%' ");

	$mf_fa_groups['fa'] = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type = 'blood'
                        and tg_name like '%Blood Adhesion%' ");
}


include_once(TRCPDD_PATH . 'pages/formdata.php');

$all_tests = array();
$all_groups = array();
$reflex_ids = array();


$ts = $wpdb->get_results("select te_ID, 
                            te_name, 
                            te_label_barcode, 
                            te_group_id 
                            from ci_test_panels");
foreach ($ts as $t) {
	$all_tests[$t->te_ID] = $t;
}
$ts = $wpdb->get_results("select * from ci_test_groups");
foreach ($ts as $t) {
	$all_groups[$t->tg_ID] = $t;
	foreach (explode(',', $t->tg_reflex_group_ID) as $rlid) {
		if ($rlid > 0) {
			$reflex_ids[$rlid] = 'GROUP_' . $rlid;
		}
	}
}

if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'labmanager', 'lab', 'lab_tech', 'lab_rep', 'lab_rep_w2'))) {
	$editable = true;
} else if (tr_current_role_in(array('labsecman', 'leadlabsecman'))) {
	$editable = true;
}


$show_spec_issue    = true;
$clinics            = array();

$today              = date_i18n('Y-m-d');

$conditions 		= trpdd_get_batch_conditions(0, '');
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$where      = ' is_saved = 0 ' . $conditions['where'];
$where_cli  = $conditions['where_clinic'];
if ($conditions['check_author']) {
	$where      .= $return['where_author'];
}

$archive_date       = tr_get_option('ci_pdf_data', 'archive_date', '');
$show_archive       = isset($_GET['showarchive']);
if (!empty($archive_date) && !$show_archive) {
	$where .= " and ba.batch_created > '{$archive_date}' ";
}

$tabs = array(
	'batch000' => 'Inbound',
	'batches' => 'Batches',
	'current_batches' => 'Current Batch',
);

switch ($current_role) {
	case 'administrator_p':
	case 'administrator':
	case 'AdminCIDW':
		$tabs['short_loadlist'] = 'Short Tox';
		$tabs['short_pcr'] = 'Short PCR';
		$tabs['short_blood'] = 'Short Blood';
		$tabs['today_batch'] = 'Today\'s Batch';
		$tabs['check_reference'] = 'Check Reference';
		$tabs['loadlist_send'] = 'Loadlist Send';

		break;
	case 'labb':
		$tabs['short_loadlist'] = 'Short Tox';
		$tabs['short_pcr'] = 'Short PCR';
		$tabs['short_blood'] = 'Short Blood';
		$tabs['today_batch'] = 'Today\'s Batch';
		break;
	case 'lab_tech':
		$tabs['short_loadlist'] = 'Short Tox';
		$tabs['short_pcr'] = 'Short PCR';
		$tabs['short_blood'] = 'Short Blood';
		$tabs['check_reference'] = 'Check Reference';
		break;
	case 'lab_sec':
		$tabs = array(
			'batch000' => 'Inbound',
			'batches' => 'Batches',
			'current_batches' => 'Current Batch',
			'short_loadlist' => 'Short Tox',
			'short_pcr' => 'Short PCR',
			'short_blood' => 'Short Blood',
		);
		break;
	case 'lab':
		$tabs['short_loadlist'] = 'Short Tox';
		$tabs['short_pcr'] = 'Short PCR';
		$tabs['short_blood'] = 'Short Blood';
		$tabs['today_batch'] = 'Today\'s Batch';
		$tabs['check_reference'] = 'Check Reference';
		break;
	case 'labmanager':

		$tabs['loadlist_send'] = 'Loadlist Send';
		$tabs['check_reference'] = 'Check Reference';
		$tabs['short_loadlist'] = 'Short Tox';
		$tabs['short_pcr'] = 'Short PCR';
		$tabs['short_blood'] = 'Short Blood';
		break;

	case 'scientist':
		$tabs['check_reference'] = 'Check Reference';
		$tabs['short_loadlist'] = 'Short Tox';
		$tabs['short_pcr'] = 'Short PCR';
		$tabs['short_blood'] = 'Short Blood';
		break;
	case 'leadlabsecman':
	case 'labsecman':
		$tabs['short_loadlist'] = 'Short Tox';
		$tabs['short_pcr'] = 'Short PCR';
		$tabs['short_blood'] = 'Short Blood';
		$tabs['today_batch'] = 'Today\'s Batch';
		$tabs['loadlist_send'] = 'Loadlist Send';
		break;
	case 'RefManager':
		$tabs = array(
			'current_batches' => 'Current Batch',
		);
		$show_spec_issue = false;
		break;
}

$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where  {$where_cli} order by cli_clinic asc");

$b_statuses = [];
foreach ($access_btypes as $abtype) {
	$b_statuses[$abtype] = tr_pdd_get_batch_statuses(false, false, false, $abtype);
}
$disable_statuses = [];
$is_new_batch_status = tr_get_option('ci_pdf_data', 'new_batch_status');
if ($is_new_batch_status) {
	foreach ($access_btypes as $abtype) {
		$disable_statuses[$abtype] = tr_pdd_get_batch_statuses_disabled_keys($abtype, 'show_in_inbound');
	}
}


if (!in_array('tox', $access_btypes)) {
	unset($tabs['short_loadlist']);
}

if (!in_array('pcr', $access_btypes)) {
	unset($tabs['short_pcr']);
}

if (!in_array('blood', $access_btypes)) {
	unset($tabs['short_blood']);
}

if ($show_spec_issue) {
	$tabs['specimen_issues'] = 'Specimen Issues';
}

if ($user->user_login == 'Administrat0r') {
	$tabs['move_ref_lab'] = 'Move Ref Lab';
}

if (isset($_GET['debug'])) {
	$tabs['docs'] = 'Manager Docs';
	$tabs['payer_list'] = 'Payers';
}


$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (!isset($tabs[$tab])) {
	$tab = key($tabs);
}
if (!isset($_REQUEST['trajax'])) {
?>
	<h2 class="nav-tab-wrapper">
		<?php

		foreach ($tabs as $k => $txt) {
			$class = $tab == $k ? 'nav-tab-active' : '';
			$link  = Tr_Base_Class_V6::link(array(
				'tab'      => $k,
				'act'      => '',
				'id'       => '',
				'batch_id' => '',
				'view'     => '',
				's'        => '',
				'search'   => '',
				'paged'    => '',
				'from'     => '',
				'to'       => '',
				'summary'  => '',
				'orderby'  => '',
				'order'    => '',
				'assen'    => '',
				'status'   => '',
				'city'     => '',
				'short'     => '',
				'cid'     => '',
				'lid'     => '',
			), false);
			echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
		}
		?>
	</h2>
	<br>
	<?php
}


$allow_bulk_actions = in_array($tab, array('batch000', 'batches', 'current_batches'));

$order 		= isset($_REQUEST['order']) && $_REQUEST['order'] == 'asc' ? 'asc' : 'desc';
$order_by 	= isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'ba.position asc, ba.mid';
$order_by 	.= ' ' . $order;

$last_batch_id = $new_batch_id = tr_pdd_get_current_batch_id();

//check exist
if (!$wpdb->get_var("select count(*) from ci_batch_ids where batch_id = '{$last_batch_id}' ")) {
	$wpdb->query("insert IGNORE into ci_batch_ids set batch_id = '{$last_batch_id}' ");
}


if ($act == 'move_sample' || (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'move_sample')) {
	include_once(trpdd_get_file_path('inbound', 'form_move_sample'));
	return;
} else if ($tab == 'loadlist_send') {
	include_once(trpdd_get_file_path('inbound', 'loadlist_send'));
	return;
}
if ($tab == 'specimen_issues') {
	include_once(trpdd_get_file_path('inbound', 'specimen_issues'));
	return;
} else if ($tab == 'move_ref_lab') {
	include_once(trpdd_get_file_path('inbound', 'move_ref_lab'));
	return;
} else if ($tab == 'payer_list') {
	$tr_no_delete = true;
	include_once(trpdd_get_file_path('settings', 'payer_list'));
	return;
} else if ($act == 'edit') {
	$hide_tabs = true;
	$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';
	if (strpos($_REQUEST['id'], '_') > 0) {
		list($btype, $id) = explode('_', $_REQUEST['id']);
		if ($id > 0) {
			$_REQUEST['id'] = $_GET['id'] = $id;
		}
	} else if (stripos($tab, 'short_') !== false) {
		if ($tab == 'short_loadlist') {
			$btype = 'tox';
		} else {
			$btype = str_replace('short_', '', $tab);
		}

		$_GET['short'] = 1;
	}
	$_REQUEST['tab'] = '';
	include_once(tr_pdd_get_req_file($btype, isset($_GET['short'])));
	return;
} else if ($tab == 'short_blood') {

	include_once(tr_pdd_get_req_file('blood', true));
	return;
} else if ($tab == 'short_pcr') {

	include_once(tr_pdd_get_req_file('pcr', true));
	return;
} else if ($tab == 'short_loadlist' || ($act == 'edit' && $_REQUEST['id'] > 0)) {

	include_once(tr_pdd_get_req_file('tox', true));
	return;
} else if ($tab == 'batch000') {
	$batch_id = 0;
	$readonly = false;
	$batch_date = get_option('tr_new_batch_date', '');



	if (empty($status)) {
		$where .= " and ba.batch_status = '' ";
	} else if ($status == 'all') {
		$where .= " and ba.batch_status != 'archived' ";
		$canceled_status = $wpdb->get_var("select s_key from ci_batches_status 
			where s_type = 'ca' and istatus = 1 
			order by iorder asc limit 1");
		if (!empty($canceled_status)) {
			$where .= " and ba.batch_status != 'ca_{$canceled_status}' ";
		}
	} else {
		$where .= " and ba.batch_status = '{$status}' ";
	}


	$new_batch_id = tr_pdd_get_current_batch_id();
	$readonly = true;

	if (tr_get_option('ci_pdf_data', 'inb_exibo')) { ?>
		<div style="padding-bottom:10px">
			<a class="button bsty1 export_batches_btn" data-type="all">Export</a>
			<?php
			if (in_array('tox', $access_btypes)) {
			?>
				<a class="button export_batches_btn bsty1" data-type="current_qc">QC Export</a>
				<a class="button export_batches_btn bsty2" data-type="etg">QC ETG Export</a>
				<a class="button export_batches_btn bsty3" data-type="current" data-ms="1" data-t="urine">MS Export Urine</a>
				<a class="button export_batches_btn bsty4" data-type="current" data-ms="1" data-t="oral">MS Export Oral</a>
				<?php /*<a class="button export_batches_btn bsty1" data-type="screening_pos">POS QC</a>*/ ?>
			<?php
			}
			if (in_array('tox', $access_btypes) || in_array('pcr', $access_btypes)) { ?>
				&nbsp;
				<a class="button export_qc">Export QC</a>
				<select id="export_qc">
					<?php
					$exportms = array();
					if (in_array('tox', $access_btypes)) {
						$exportms['current_qc'] = 'Tox QC';
						$exportms['etg'] = 'ETG';
						$exportms['screening_pos'] = 'POS Screening';
					}
					if (in_array('pcr', $access_btypes)) {
						$exportms['pcr'] = 'PCR';
					}

					foreach ($exportms as $k => $txt) {
						echo '<option value="' . $k . '">' . $txt . '</option>';
					}
					?>
				</select>
				&nbsp;&nbsp;
				&nbsp;
				<script>
					jQuery(function($) {
						$('a.export_qc').click(function() {
							var $t = $('select#export_qc').val();
							if ($t == 'pcr') {
								location = '?tr_action=export_pcr_qc&batch_id=<?php echo $batch_id ?>';
							} else {
								location = '?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=' + $t;
							}
							return false;
						})
					})
				</script>
			<?php }

			if (in_array('blood', $access_btypes) && tr_get_option('ci_pdf_data', 'inb_exmf')) {
			?>
				<a class="button export_batches_btn" data-type="blood_mf">Export MF</a>
				<a class="button export_batches_btn" data-type="blood_fa">Export FA</a>
			<?php
			}
			if (tr_get_option('ci_pdf_data', 'inb_exbat')) {
			?>
				<a class="button export_batches_btn" data-type="allf">Export Batch</a>
			<?php
			}
			if (tr_get_option('ci_pdf_data', 'inb_exbatrefed') && ($is_ci_admin || tr_current_role_in(array('AdminCIDW')))) {
			?>
				<a class="button export_batches_btn" data-type="reflabtransfered">RefLab Transfer Export</a>
			<?php
			}
			?>

		</div>
		<script>
			jQuery(function($) {
				$('body').on('click', 'a.export_batches_btn', function() {
					var $t = $(this).data('t');
					var $ms = $(this).data('ms');
					var $url = '?tr_action=ci_export_batches&batch_id=0&type=' + $(this).data('type');
					if ($t) {
						$url += '&t=' + $t;
					}
					if ($ms) {
						$url += '&ms';
					}
					location = $url;
					return false;
				})
			})
		</script>
	<?php
	}
	?>
	<?php Tr_Session_Class::show() ?>

<?php
} else if ($tab == 'current_batches') {
	$batch_id = $last_batch_id;
	$row_per_page = 300;
?>
	<?php Tr_Session_Class::show() ?>
	<div>
		Batch#: <?php echo $batch_id ?>
		<a class="button bsty1" href="?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=all">Export</a>
		<?php if (in_array('tox', $access_btypes)) { ?>
			<a class="button bsty2" href="?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=current&ms">MS Export</a>
			<a class="button bsty3" href="?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=current&ms&t=urine">MS Export Urine</a>
			<?php if (!tr_get_option('ci_pdf_data', 'disable_Oral')) : ?>
				<a class="button bsty4" href="?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=current&ms&t=oral">MS Export Oral</a>
			<?php endif; ?>
			<a class="button bsty1" href="?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=current&ms&t=ref_urine">Ref MS Export Urine</a>
			<?php if (!tr_get_option('ci_pdf_data', 'disable_Oral')) : ?>
				<a class="button bsty2" href="?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=current&ms&t=ref_oral">Ref MS Export Oral</a>
			<?php endif; ?>
		<?php } ?>
		<?php if (in_array('blood', $access_btypes)) { ?>
			<a class="button bsty1" href="?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=blood&t=blood">Export Blood</a>
			<a class="button bsty3" href="?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=current&t=ref_blood">Ref Export Blood</a>
		<?php } ?>
		<?php /** if(!tr_get_option('ci_pdf_data','disable_Alcohol')):?>
		<a class="button" href="?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=current&m=alcohol">Export Alcohol</a>
		<?php endif;*/ ?>
		<?php if (!tr_get_option('ci_pdf_data', 'disable_dl') && in_array('tox', $access_btypes)) : ?>
			<a class="button thickbox bsty4" href="?&tr_action=get_all_bet&ms&width=300">MS Export MethDL</a>
		<?php endif; ?>

		<?php
		if (tr_get_option('ci_pdf_data', 'inb_exbatrefed') && ($is_ci_admin || tr_current_role_in(array('AdminCIDW')))) {
		?>
			<a class="button export_batches_btn" href="?tr_action=ci_export_batches&batch_id=<?php echo $batch_id ?>&type=reflabtransfered">RefLab Transfer Export</a>
		<?php
		}
		?>
	</div>
	<hr class="clear">
<?php
} else if ($tab == 'docs') {
	include_once(trpdd_get_file_path('doctor', 'list'));
	return;
} else if ($tab == 'today_batch') {
	include_once(trpdd_get_file_path('inbound', 'today_batch'));
	return;
} else if ($tab == 'check_reference') {
	include_once(trpdd_get_file_path('inbound', 'check_reference'));
	return;
} else {


	$where .= " and batch_id > 0 ";
	$bids = $wpdb->get_col("select batch_id from ci_batch_ids group by batch_id
	                        order by batch_id desc LIMIT 500");
	if (empty($bids)) {
		include_once(TRCPDD_PATH . 'inc/functions_fix.php');
		trpdd_sync_batch_ids();
	}

?>
	<?php Tr_Session_Class::show() ?>
	<form method="get" style="float:left;">
		<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
		<label>Batch #</label>
		<select name="batch_id" class="njs_select2">
			<?php
			foreach ($bids as $bid) {
				if (empty($bid) || $bid == 'na' || substr($bid, 0, 1) == 'b') continue;
				$selected = isset($_REQUEST['batch_id']) && $bid == $_REQUEST['batch_id'] ? 'selected' : '';
				echo '<option value="' . $bid . '" ' . $selected . '>' . $bid . '</option>';
			}
			?>
		</select>
		<input type="submit" name="view" value="View" class="button-primary">
		<a class="button bsty1 export_batches_btn" data-type="all">Export</a>
		<?php
		if (in_array('tox', $access_btypes)) {
		?>
			<a class="button export_batches_btn bsty1" data-type="current_qc">QC Export</a>
			<a class="button export_batches_btn bsty2" data-type="etg">QC ETG Export</a>
			<a class="button export_batches_btn bsty3" data-type="current" data-ms="1" data-t="urine">MS Export Urine</a>
			<a class="button export_batches_btn bsty4" data-type="current" data-ms="1" data-t="oral">MS Export Oral</a>
			<?php /*<a class="button export_batches_btn bsty1" data-type="screening_pos">POS QC</a>*/ ?>
		<?php
		}

		if (in_array('tox', $access_btypes) || in_array('pcr', $access_btypes)) { ?>
			&nbsp;
			<a class="button export_qc">Export QC</a>
			<select id="export_qc">
				<?php
				$exportms = array();
				if (in_array('tox', $access_btypes)) {
					$exportms['current_qc'] = 'Tox QC';
					$exportms['etg'] = 'ETG';
					$exportms['screening_pos'] = 'POS Screening';
				}
				if (in_array('pcr', $access_btypes)) {
					$exportms['pcr'] = 'PCR';
				}

				foreach ($exportms as $k => $txt) {
					echo '<option value="' . $k . '">' . $txt . '</option>';
				}
				?>
			</select>
			&nbsp;&nbsp;
			&nbsp;
			<script>
				jQuery(function($) {
					$('a.export_qc').click(function() {
						var $t = $('select#export_qc').val();
						if ($t == 'pcr') {
							location = '?tr_action=export_pcr_qc&batch_id=' + $('[name="batch_id"]').val() + '';
						} else {
							location = '?tr_action=ci_export_batches&batch_id=' + $('[name="batch_id"]').val() + '&type=' + $t;
						}
						return false;
					})
				})
			</script>
		<?php }

		if (in_array('blood', $access_btypes) && tr_get_option('ci_pdf_data', 'inb_exmf')) {
		?>
			<a class="button export_batches_btn" data-type="blood_mf">Export MF</a>
			<a class="button export_batches_btn" data-type="blood_fa">Export FA</a>
		<?php
		}
		if (tr_get_option('ci_pdf_data', 'inb_exbat')) {
		?>
			<a class="button export_batches_btn" data-type="allf">Export Batch</a>
		<?php
		}
		if (tr_get_option('ci_pdf_data', 'inb_exbatrefed') && ($is_ci_admin || tr_current_role_in(array('AdminCIDW')))) {
		?>
			<a class="button export_batches_btn" data-type="reflabtransfered">RefLab Transfer Export</a>
		<?php
		}
		?>

		<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'move_sample')) ?>">Move Sample</a>
	</form>
	<script>
		jQuery(function($) {
			$('body').on('click', 'a.export_batches_btn', function() {
				var $t = $(this).data('t');
				var $ms = $(this).data('ms');
				var $url = '?tr_action=ci_export_batches&batch_id=' + $('[name="batch_id"]').val() + '&type=' + $(this).data('type');
				if ($t) {
					$url += '&t=' + $t;
				}
				if ($ms) {
					$url += '&ms';
				}
				location = $url;
				return false;
			})
		})
	</script>



	<div class="clear"></div>
	<hr class="clear">
<?php
}


?>

<form method="get" style="float:left;margin-right: 10px;padding-top:5px">
	<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
	<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
	<?php if (empty($tab)) : ?>
		<input type="hidden" name="batch_id" value="<?php echo isset($_REQUEST['batch_id']) ? $_REQUEST['batch_id'] : '' ?>" />
	<?php endif; ?>

	<input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" placeholder="Name/Accession" />
	<input type="search" name="city" value="<?php echo $city_selected ?>" placeholder="City" />
	<input type="search" class="hasdate" name="doc" value="<?php echo $doc_selected ?>" placeholder="Doc" style="width:110px" />

	<select name="cid" class="njs_select2" style="width:220px">
		<option value="">Select Clinic</option>
		<?php
		foreach ($clinics as $cli) {
			$selected = $cli->cID == $clinic_selected ? 'selected' : '';
			echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
		}
		?>
	</select>

	<?php if (tr_get_option('ci_pdf_data', 'inb_lab_ref')) { ?>
		<select name="lid">
			<option value="">All Lab</option>
			<?php
			$labs = $wpdb->get_results("select * from ci_labs where lab_name!='' order by lab_is_ref asc, lab_name asc");
			foreach ($labs as $lab) {
				if ($lab->lab_is_ref > 0) {
					$lab->lab_ID = 'ref_' . $lab->lab_ID;
					$lab->lab_name = 'Ref: ' . $lab->lab_name;
				}
				$selected = $lab->lab_ID == $lab_selected ? 'selected' : '';
				echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_name . '</option>';
			}
			?>
		</select>
	<?php } ?>


	<?php if ($tab == 'batch000') { ?>

		<select name="status" id="statuslist" class="njs_select2" style="width:220px">
			<option value="">None</option>
			<?php
			$status_all = tr_pdd_get_batch_statuses();
			$status_all = array_merge(array('all' => 'All Status'), $status_all);
			unset($status_all['']);
			unset($status_all['cold_storage']);
			foreach ($status_all as $s => $v) {
				$selected = $status == $s ? 'selected' : '';
				echo '<option value="' . $s . '" ' . $selected . '>' . $v . '</option>';
			}
			?>
		</select>
	<?php

		if (!empty($archive_date)) {
			echo '<label><input type="checkbox" name="showarchive" onclick="this.form.submit()" value=1 ' . checked($show_archive, true, false) . '/> Show Archived</label>';
		}
	} ?>

	<input type="submit" class="button bsty6" value="Filter" />

	<?php if ($tab == 'batch000' && !empty($archive_date)) { ?>
		<input type="button" class="button bsty4" onclick="jQuery('#statuslist').val('archived');this.form.submit()" value="Show Archived" />
	<?php } ?>
</form>
<span class="right" style="padding-left:10px;"> Last Batch Number: <?php echo $last_batch_id ?></span>
<?php

if (isset($batch_id) || !empty($_REQUEST['s']) || !empty($city_selected) || !empty($doc_selected) || $clinic_selected > 0) {

	$packages = array();

	if (CI_Base_Lab::has_user_labs()) {
		$lab_str = CI_Base_Lab::get_user_lab_ids_str();
		$where .= " and (ba.lab_id in ( {$lab_str} ) or ba.ref_lab_ID in ({$lab_str}) )";
	}

	if ($ref_lab_ID > 0) {
		$where .= " and ba.ref_lab_ID={$ref_lab_ID} ";
	} else if ($lab_selected > 0) {
		$where .= " and (ba.lab_id = {$lab_selected} or ba.ref_lab_ID={$lab_selected})";
	}

	if (isset($batch_id) && $batch_id !== '') {
		if ($batch_id === 0) {
			$where .= " and (ba.batch_id = '{$batch_id}' or ba.batch_id='')";
		} else {
			$arr_bid = explode('-', $batch_id);
			if (isset($arr_bid[1]) && $arr_bid[0] > 0 && $arr_bid[1] === '1') {
				$where .= " and (ba.batch_id = '{$batch_id}' or ba.batch_id = '{$arr_bid[0]}') ";
			} else {
				$bids = array($batch_id);
				if ($tab == 'batches' && tr_get_option('ci_pdf_data', 'multi_batch_id')) {
					for ($i = 1; $i <= 10; $i++) {
						$bids[] = $batch_id . '-' . $i;
					}
				}
				$bids = implode("','", $bids);
				$where .= " and ba.batch_id in ( '{$bids}' ) ";
			}


			$batch_date = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", '$1-$2-$3', $batch_id);
			$rs = $wpdb->get_results("select rb.mid,rb.btype,
	                        re.re_tracking_number_barcode
	                        from ci_receiving_batches rb
	                        join ci_receiving re ON re.re_ID = rb.re_ID
	                        where re.re_today = '{$batch_date}' ");
			foreach ($rs as $r) {
				$packages[$r->btype . '_' . $r->mid] = $r->re_tracking_number_barcode;
			}
		}
	}

	if (!empty($_REQUEST['s'])) {
		if (stripos($_REQUEST['s'], 'TYPE:') === 0) {
			$batch_type = str_ireplace('type:', '', $_REQUEST['s']);
			$where .= " and ba.batch_type='{$batch_type}' ";
		} else {

			if (strpos($_REQUEST['s'], ',') > 0) {
				list($firstname, $lastname) = explode(',', $_REQUEST['s']);
				$firstname = trim($firstname);
				$lastname = trim($lastname);
				$explodeString = "%{$firstname}%{$lastname}%";
				$where .= $wpdb->prepare(" and (ba.patient_name like %s)", $explodeString);
			} else {
				$where .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen like %s)", "%{$_REQUEST['s']}%", "{$_REQUEST['s']}%");
			}
		}
	}

	if (!empty($city_selected)) {
		$where .= $wpdb->prepare(" and cli.cli_city like %s ", $city_selected . '%');
	}

	if (!empty($doc_selected)) {
		$where .= " and ba.doc >='{$doc_selected}' and ba.doc <='{$doc_selected} 23:59:59'";
	}

	if ($clinic_selected > 0) {
		$where .= " and ba.cli_ID = {$clinic_selected} ";
	}

	$pagination = new Tr_Pagination_class($row_per_page);
	$sql = '';

	if ($tab == 'batches' || $tab == 'current_batches' || $tab == 'batch000') {
		$order_by = str_replace('ba.', 'ball.', $order_by);

		$more_fields = '';
		$joins = '';
		if ($show_paid) {
			$more_fields .= " ba.paid, ";
		}
		if ($show_stat) {
			$more_fields .= " ba.stat, ";
		}
		if ($inb_insmid || $inb_insgid) {
			$more_fields .= " ins.ins_member_id, ins.ins_group_id, ";
			$joins .= " left join ci_insurances ins ON ins.ins_ID = ba.insurance1 ";
		}

		$child_sql = array();
		$total = 0;
		foreach ($access_btypes as $btype) {
			if ($btype == 'study') continue;

			if ((!empty($btype_selected) && $btype != $btype_selected) || !isset($btype_colors[$btype])) {
				continue;
			}
			$batch_tbl = tr_pdd_get_table_by_btype($btype);

			$total += $wpdb->get_var("select count(*) as c from {$batch_tbl} ba 
			                left JOIN ci_clinic cli ON cli.cID = ba.cli_ID
			                where {$where}");

			$more_fields_row = '';
			if (tr_get_option('ci_pdf_data', 'inb_showmf_fa')) {
				if ($btype == 'blood') {
					$more_fields_row .= ' ba.blood_fa, ba.blood_mf, ';
				} else {
					$more_fields_row .= "'' as blood_fa, '' as blood_mf, ";
				}
			}

			$child_sql[] = "
	        ( 
	            SELECT 
                ba.mid,ba.position,ba.batch_id,ba.batch_type,ba.batch_status,ba.assen,ba.oldreq,ba.patient_name,ba.current_medication,pa.pa_dob,
                ba.requesting_practice,ba.requesting_physician,ba.physician_phone,ba.insurance,ba.doc,ba.spec_received,ba.type_of_specimen,
                ba.current_medication_other,ba.subscriber_insurance,ba.panel_tests_requested,ba.notes,ba.qc, ba.culture,ba.billing_notes,
                ba.ref_lab_ID,ba.lab_id,
                {$more_fields}
                {$more_fields_row}
                '{$btype}' as btype,
                
                ref_lab.lab_dba_init as lab_ref,
                lab.lab_dba_init as lab,
                cli.cli_city ,
                pa.pa_first,
                pa.pa_last,
                pa.pa_ssn
                
                from {$batch_tbl} ba
                left JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
                left JOIN ci_labs ref_lab ON ref_lab.lab_ID = ba.ref_lab_ID
                left JOIN ci_clinic cli ON cli.cID = ba.cli_ID 
                left join ci_patients pa ON pa.pa_ID = ba.patient_id 
                {$joins}
                where {$where} group by ba.mid )";
		}

		$pagination->setTotal($total);
		$offset = $pagination->offset();

		$child_sql = implode(' UNION ALL ', $child_sql);
		$sql = "
            select ball.* from ( {$child_sql} ) AS ball
                 order by btype desc,{$order_by} 
                 limit {$offset},{$row_per_page}";

		//$settotal = true;
	} else {

		$total = $wpdb->get_var("select count(*) as c from ci_batches ba 
		                        left JOIN ci_clinic cli ON cli.cID = ba.cli_ID
		                        where {$where}");

		$pagination->setTotal($total);
		$offset = $pagination->offset();

		$sql = "
                select ba.*,
                ref_lab.lab_dba_init as lab_ref,
                lab.lab_dba_init as lab,
                cli.cli_city,
                pa.pa_dob,
                pa.pa_first,
                pa.pa_last
                from ci_batches ba
                left JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
                left JOIN ci_labs ref_lab ON ref_lab.lab_ID = ba.ref_lab_ID
                left JOIN ci_clinic cli ON cli.cID = ba.cli_ID 
                left join ci_patients pa ON pa.pa_ID = ba.patient_id
                where {$where} group by ba.mid order by {$order_by} limit {$offset},{$row_per_page}";
	}


	if (isset($_GET['debug'])) {
		echo '<br><br>';
		echo $sql;
		echo '<br><br>';
	}
	$batches = $wpdb->get_results($sql);

	if ($settotal) {
		$pagination = new Tr_Pagination_class(100000);
		$pagination->setTotal(count($batches));
	}
	// echo $sql;

	$ref_labs = $wpdb->get_results("select * from ci_labs 
	where (lab_is_ref=1 or lab_is_ref=2)
	 order by lab_name asc");

	$total_col = 20;
?>
	<div class="tablenav top" style="padding-top:15px">
		<?php
		if (count($access_btypes) > 1) {
			echo '<div class="tips left" style="line-height:30px">';
			foreach ($access_btypes as $bty) {
				if ($bty == 'study') continue;
				$color = isset($btype_colors[$bty]) ? $btype_colors[$bty] : '';
				echo ' [ <a class="btcolor" href="' . Tr_Base_Class_V6::link(array('qbtype' => $bty), false) . '" style="color:' . $color . '">' . tr_pdd_get_typename($bty) . '</a> ] ';
			}
			echo '</div>';
		}
		?>

		<?php if ($allow_bulk_actions) { ?>
			<div class="left" style="margin-left:15px;">
				<select id="bulk_action_select">
					<option value="">Bulk actions</option>
					<option value="move_to_archived">Move to Archived</option>
					<?php if (count($ref_labs) > 0) { ?>
						<option value="move_to_reflab">Move to Ref Lab</option>
						<option value="remove_reflab">Move to main Lab</option>
					<?php } ?>
					<?php if ($can_delete_bulk) { ?>
						<option value="delete_selected">Delete selected</option>
					<?php } ?>
				</select>
				<?php if (count($ref_labs) > 0) { ?>
					<select id="ref_lab_id" style="display:none">
						<?php
						foreach ($ref_labs as $ref_lab) {
							echo '<option value="' . $ref_lab->lab_ID . '">' . $ref_lab->lab_name . '</option>';
						}
						?>
					</select>
				<?php } ?>
				<a href="#" class="button" id="do_bulk_action">Apply</a>
				<span class="count_items_selected"></span>
			</div>
		<?php } ?>
		<?php echo $pagination->pagination() ?>
	</div>

	<div class="wraptbl">
		<table class="wp-list-table widefat striped pages blue_tbl hover_tbl <?php if ($allow_bulk_actions) echo ' selecttbl';
																				if ($status == 'archived' && !empty($archive_date)) echo ' tblarchived' ?>" style="table-layout: auto;width:200%">
			<thead class="sticky">
				<tr>
					<th style="width:100px">#</th>
					<?php tr_tbl_sort('ba.patient_name', 'Patient Name', '', 'width:160px') ?>
					<?php tr_tbl_sort('ba.doc', 'Collection', '', 'width:100px') ?>
					<?php if ($tab == '') {
						$total_col++;
					?>
						<?php tr_tbl_sort('ba.batch_id', 'Batch') ?>
					<?php } ?>
					<?php tr_tbl_sort('ba.assen', 'Accession Number', '', 'width:160px') ?>
					<th></th>
					<?php
					if (tr_get_option('ci_pdf_data', 'inb_oldreq')) {
						echo '<th style="width:90px">' . tr_get_option('ci_pdf_data', 'title_pid_field', 'PID') . '</th>';
						$total_col++;
					}
					if ($show_paid) { ?>
						<th>Paid</th>
					<?php
					}

					if (in_array($tab, $list_tab_show_qc)) {
						if (tr_get_option('ci_pdf_data', 'inb_showmf_fa')) {
							echo '<th>FA<br><input type="checkbox" class="mashcol" data-type="blood_fa" style="margin:0"/></th>';
							echo '<th>MF<br><input type="checkbox" class="mashcol" data-type="blood_mf" style="margin:0"/></th>';
							$total_col++;
							$total_col++;
						} else {
							echo '<th>QC</th>';
							$total_col++;
						}
					}
					?>
					<?php if ($mv_bref_top) { ?>
						<th style="width:3%">Referenced</th>
						<?php tr_tbl_sort('ba.batch_status', 'Status', '', 'min-width: 100px') ?>
					<?php } ?>
					<?php
					if ($inb_rv_mp) {
						echo '<th style="width:10%;min-width:240px">Req Panel Tests</th>';
					} else if (!$hide_col_medication) {
						tr_tbl_sort('current_medication', 'Medication', '', 'width:7%');
					}
					?>
					<?php if ($inb_ccbdob) {
						tr_tbl_sort('cli_city', 'Clinic City', '', 'width:5%;min-width:100px');
					} ?>
					<?php tr_tbl_sort('pa_dob', 'DOB', '', 'width:5%;min-width:90px') ?>
					<?php tr_tbl_sort('requesting_practice', 'Clinic', '', 'width:10%') ?>
					<?php if (!$inb_ccbdob) {
						tr_tbl_sort('cli_city', 'Clinic City', '', 'width:5%;min-width:100px');
					} ?>
					<?php if ($inb_ssn) {
						tr_tbl_sort('pa_ssn', 'SSN', '', 'width:5%;min-width:100px');
						$total_col++;
					} ?>

					<?php tr_tbl_sort('requesting_physician', 'Dr Name', '', 'width:8%') ?>
					<?php tr_tbl_sort('physician_phone', 'Dr Phone', '', 'width:6%;min-width:100px') ?>
					<?php tr_tbl_sort('ba.insurance', 'Insurance', '', 'width:8%') ?>
					<?php if ($inb_insmid) {
						tr_tbl_sort('ins.ins_member_id', 'Ins #', '', 'width:5%;min-width:100px');
						$total_col++;
					} ?>
					<?php if ($inb_insgid) {
						tr_tbl_sort('ins.ins_group_id', 'Ins Group', '', 'width:5%;min-width:100px');
						$total_col++;
					} ?>
					<?php tr_tbl_sort('ba.spec_received', 'Received', '', 'width:6%;min-width:100px') ?>
					<th style="width:40px">Lab</th>
					<?php if (!$mv_bref_top) { ?>
						<th style="width:3%">Referenced</th>
						<?php tr_tbl_sort('ba.batch_status', 'Status', '', 'min-width: 100px') ?>
					<?php } ?>
					<?php
					if (!$inb_rv_mp) {
						echo '<th style="width:10%;min-width:240px">Req Panel Tests</th>';
					} else if (!$hide_col_medication) {
						tr_tbl_sort('ba.current_medication', 'Medication', '', 'width:7%');
					}
					?>
					<?php tr_tbl_sort('ba.type_of_specimen', 'Type of Specimen', '', 'min-width: 100px') ?>
					<?php if (!tr_get_option('ci_pdf_data', 'hide_ib_notes')) { ?>
						<th>Notes</th>
					<?php } ?>
				</tr>
			</thead>
			<tbody>
				<?php
				if (count($batches) == 0) {
				?>
					<tr>
						<td colspan="<?php echo $total_col ?>">Not found</td>
					</tr>
					<?php
				}
				$i = 1;
				$have_btypes = array();
				foreach ($batches as $row) {
					$printdata = array();
					$row_class = '';
					$can_delete_bylab = true;
					if (count($inb_disdeletelids) > 0 && in_array($row->lab_id, $inb_disdeletelids)) {
						$can_delete_bylab = false;
					}

					$package_name = '';
					$panels_print = array();

					$row->rawmid = $row->mid;
					if (isset($packages[$row->btype . '_' . $row->mid])) {
						$package_name = $packages[$row->btype . '_' . $row->mid];
					}

					$color_type = $btype_colors[$row->btype];
					if ($row->btype != 'tox') {
						$row->mid = $row->btype . '_' . $row->mid;
					}


					//for mat patients name to last,first
					if (strpos($row->patient_name, ',') === false) {
						list($fname, $lname) = explode(' ', $row->patient_name . ' ', 2);
						$lname = trim($lname);
						$fname = trim($fname);
						$row->patient_name = ucfirst(strtolower($lname)) . ', ' . ucfirst(strtolower($fname));
					} else {
						list($lname, $fname) = explode(',', $row->patient_name, 2);
						$lname = trim($lname);
						$fname = trim($fname);
						$row->patient_name = ucfirst(strtolower($lname)) . ', ' . ucfirst(strtolower($fname));
					}

					//format medication
					$row->current_medication = str_replace('|', ',', $row->current_medication);
					$medications = explode(',', trim($row->current_medication));
					if (!empty($row->current_medication_other)) {
						$row->current_medication_other = str_replace('|', ',', $row->current_medication_other);
						$others = explode(',', trim($row->current_medication_other));
						$medications = array_merge($medications, $others);
					}
					foreach ($medications as $j => &$med) {
						$med = trim($med);
						$med = ucfirst(strtolower($med));
						if (empty($med)) {
							unset($medications[$j]);
						}
					}
					$medications = implode(',', $medications);
					$medications = str_replace(',', ', ', $medications);

					//check insurance_name
					if (empty($row->insurance)) {
						$row->insurance = $row->subscriber_insurance;
					}

					//get panel

					$row_group_ids = array();
					if ($row->btype != 'tox') {
						$panel = array();
						$has_groups = false;
						foreach (explode(',', $row->panel_tests_requested) as $t) {
							if (stripos($t, 'group') === false) {
								if ($t > 0 && isset($all_tests[$t])) {
									if (!$has_groups) {
										$panel[] = $all_tests[$t]->te_name;
									}


									if (isset($all_tests[$t]->te_label_barcode) && !empty($all_tests[$t]->te_label_barcode)) {
										$panels_print[$t] = $all_tests[$t]->te_label_barcode;
									}
								}
							} else {
								$group_id = str_ireplace('group_', '', $t);
								$row_group_ids[] = $group_id;

								if (isset($all_groups[$group_id])) {
									$has_groups = true;
									$panel[] = $all_groups[$group_id]->tg_name;

									if (isset($all_groups[$group_id]->tg_label_name) && !empty($all_groups[$group_id]->tg_label_name)) {
										$panels_print[$t] = $all_groups[$group_id]->tg_label_name;
									} else if (isset($all_groups[$group_id]->tg_notestcheckbox) && $all_groups[$group_id]->tg_notestcheckbox) {
										foreach ($all_tests as $test) {
											if ($test->te_group_id == $group_id && !empty($test->te_label_barcode)) {
												$panels_print[$test->te_ID] = $test->te_label_barcode;
											}
										}
									}
								}
							}
						}
						if ($row->culture) {
							$panel[] = 'Culture';
						}
						$panel = implode(', ', $panel);
					} else if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
						$panel = $row->panel_tests_requested;
					} else if (strpos($row->panel_tests_requested, '+') === false && !empty($row->custom_profile)) {
						$panel = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
					} else {
						$panel = $row->panel_tests_requested;
					}


					if (!in_array($row->btype, $have_btypes)) {
						$have_btypes[] = $row->btype;
					?>
						<tr>
							<th colspan="<?php echo $total_col ?>" style="background: #c1c5ce;" id="block_<?php echo $row->btype ?>"><strong style="color:#fff;font-size:15px"><?php echo tr_pdd_get_typename($row->btype) ?></strong></th>
						</tr>
					<?php
					}
					$print_times = tr_get_option('ci_pdf_data', $row->btype . '_print_times', 1);
					if ($print_times <= 0) $print_times = 1;
					$cul = '';
					$printpanels = '';
					if ($row->btype == 'gene') {
						$printt = tr_get_option('ci_pdf_data', 'gene_print_times', 1);
						if ($printt > 1) {
							$print_times = $printt;
						}
						$printpanels = implode('|', $panels_print);
					} else if ($row->btype == 'blood') {
						$printt = tr_get_option('ci_pdf_data', 'blood_print_times', 1);
						if ($printt > 1) {
							$print_times = $printt;
						}
						$printpanels = implode('|', $panels_print);
					} else if ($row->btype == 'pcr') {
						$printt = tr_get_option('ci_pdf_data', 'pcr_print_times', 1);
						if ($printt > 1) {
							$print_times = $printt;
						}
						$cul = $row->culture;

						if (is_array($reflex_ids) && count($reflex_ids) > 0) {
							foreach ($reflex_ids as $rlid) {
								unset($panels_print[$rlid]);
							}
						}

						if (tr_get_option('ci_pdf_data', 'mp_lb')) {
							$printpanels = implode(' ', $panels_print);
						} else {
							$printpanels = implode('|', $panels_print);
						}
					} else if (count($panels_print) > 0) {
						$printpanels = implode('|', $panels_print);
					}

					if (isset($row->stat) && $row->stat) {
						$row_class .= ' row_stat ';
					}


					if ($row->btype == 'blood' && tr_get_option('ci_req_settings_blood', 'print_assen_specimen')) {
						foreach (explode(',', $row->type_of_specimen) as $spec) {
							if (stripos($spec, 'blood') !== false) {
								$printdata[] = array(
									'assen' => substr($row->assen, 0, 2) . 'W' . substr($row->assen, 3),
									'panel' => '',
									'position' => 'W'
								);
							} else if (stripos($spec, 'Urine') !== false) {
								$printdata[] = array(
									'assen' => substr($row->assen, 0, 2) . 'U' . substr($row->assen, 3),
									'panel' => '',
									'position' => 'U'
								);
							}
						}
					}




					?>

					<tr class="<?php echo $row_class ?>" data-printdata="<?php echo esc_attr(json_encode($printdata)) ?>" data-print_times="<?php echo $print_times ?>" data-mid="<?php echo $row->mid ?>" data-cul="<?php echo $cul ?>" data-doc="<?php echo $row->doc ?>" data-position="<?php echo $row->btype == 'tox' ? $row->position : '' ?>" data-panels_print="<?php echo $printpanels ?>">
						<td title="<?php echo $row->mid ?>" class="col-position">
							<?php echo $row->position; ?>
							<?php
							if ($row->btype == 'tox') {
								$missing_etg = false;
								$missing = false;
								$has_value = false;
								$validities = tr_pdd_get_result_validity(0, $row->batch_created, $row->mid);
								foreach ($validities as $arr) {
									if ($arr['conc'] === '' || $arr['conc'] === null) {
										$missing = true;
									} else {
										$has_value = true;
									}
								}

								if (!$missing) {
									$has_value = false;
									$screenings = tr_pdd_get_result_validity(1, $row->batch_created, $row->mid);
									foreach ($screenings as $arr) {

										if (!$arr['checked']) continue;

										if (strtolower($arr['u_name']) == 'ETG') {
											if ($arr['conc'] === '' || $arr['conc'] === null) {
												$missing_etg = true;
											}
										} else if ($arr['conc'] === '' || $arr['conc'] === null) {
											$missing = true;
										} else {
											$has_value = true;
										}
									}
								}
								echo '<div class="red">';
								if ($missing) {
									echo 'C';
								}
								if ($missing_etg) {
									echo 'A';
								}
								echo '</div>';
							}
							?>
							<?php
							if (stripos($row->billing_notes, '@Home') > 0 || stripos($row->billing_notes, ' Home ') > 0) {
								echo '<span class="dashicons dashicons-admin-home green" title="Home Kit"></span>';
							}
							?>
						</td>
						<td>
							<span class="col-patient_name"><?php echo $row->patient_name ?></span>
							<?php
							if ($tab == 'current_batches') {
								$has_quickedit = true;
							?>
								<div class="row-actions">
									<a class="editinline" href="#">Quick Edit</a> |
									<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'short' => 1)) ?>">Edit</a> |
									<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'short' => '')) ?>">Req Edit</a>
									<?php if ($can_delete && $can_delete_bylab) : ?>
										<span class="trash">
											| <a class="delete deletelink" href="#" data-href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_delete_cbatch', 'id' => $row->mid)) ?>">Delete</a>
										</span>
									<?php endif; ?>
									<?php if ($row->batch_type == 'E') : ?>
										| <a class="thickbox" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'tab' => '')) ?>&TB_iframe=true&width=800&height=550">Print</a>
									<?php endif; ?>
								</div>

							<?php
							} else if ($tab == 'batches') {
								$has_quickedit = true;
							?>
								<div class="row-actions">
									<a class="editinline" href="#">Quick Edit</a> |
									<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'short' => 1)) ?>">Edit</a> |
									<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'short' => '')) ?>">Req Edit</a>
									<?php if ($row->batch_type == 'E') : ?>
										| <a class="thickbox" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'tab' => '')) ?>&TB_iframe=true&width=800&height=550">Print</a>
									<?php endif; ?>
								</div>

							<?php
							} else if ($editable) {
							?>
								<div class="row-actions">
									<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'short' => 1)) ?>">Edit</a> |
									<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'short' => '')) ?>">Req Edit</a>
									<?php if ($can_delete && $can_delete_bylab) : ?>
										<span class="trash">
											| <a class="delete deletelink" href="#" data-href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_delete_cbatch', 'id' => $row->mid)) ?>">Delete</a>
										</span>
									<?php endif; ?>
								</div>
							<?php
							}


							if ($has_quickedit) {
							?>
								<div class="editinline_data hidden">
									<div class="inline_data">
										<fieldset class="inline-edit-col-left">
											<div class="inline-edit-col">
												<h4>Quick Edit</h4>

												<label>
													<span class="title">Position</span>
													<span class="input-text-wrap"><input type="text" value="<?php echo $row->position ?>" name="position"></span>
												</label>

												<label>
													<span class="title">Patient First Name</span>
													<span class="input-text-wrap"><input type="text" value="<?php echo $row->pa_first ?>" name="pa_first"></span>
												</label>
												<label>
													<span class="title">Patient Last Name</span>
													<span class="input-text-wrap"><input type="text" value="<?php echo $row->pa_last ?>" name="pa_last"></span>
												</label>
											</div>
										</fieldset>
										<fieldset class="inline-edit-col-right">
											<div class="inline-edit-col">
												<h4>&nbsp;</h4>
												<label>
													<span class="title">DOB</span>
													<span class="input-text-wrap"><input type="text" class="hasdate2" data-write="1" value="<?php echo $row->pa_dob ?>" name="pa_dob"></span>
												</label>
												<label>
													<span class="title">MEDICATIONS</span>
													<span class="input-text-wrap"><input type="text" value="<?php echo $row->current_medication ?>" name="current_medication"></span>
												</label>
												<label>
													<span class="title">Reference Lab</span>
													<span class="input-text-wrap"><input type="text" class="ref_lab_auto" value="<?php echo $row->lab_ref ?>" name="lab_ref"></span>
													<input type="hidden" name="ref_lab[lab_ID]" value="<?php echo $row->ref_lab_ID ?>">
												</label>
											</div>
										</fieldset>
									</div>
								</div>
							<?php
							}
							?>
						</td>
						<td><?php echo $row->doc ?></td>
						<?php if ($tab == '') { ?>
							<td><?php echo $row->batch_id; ?></td>
						<?php } ?>
						<td style="color:<?php echo $color_type ?>" title="<?php echo 'Batch#: ', $row->batch_id ?>">
							<span class="assen"><?php echo $row->assen ?></span> &nbsp;&nbsp;
							<a class="print_assen_btn" href="#">print</a>
						</td>
						<td><?php if (!empty($package_name)) { ?><span class="itip" title="<?php echo $package_name ?>">i</span><?php } ?></td>
						<?php
						if (tr_get_option('ci_pdf_data', 'inb_oldreq')) {
							echo '<td>' . $row->oldreq . '</td>';
						}
						if ($show_paid) { ?>
							<td class="center"><?php

												if ($row->paid <= -2) {
													$apis = array(
														-2 => array('EL', 'Elink'),
														-3 => array('CT', 'CTEH'),
														-4 => array('A', 'Athena'),
														-5 => array('S', 'Solv'),
														-6 => array('N', 'Noah'),
														-7 => array('M', 'Meenta'),
														-8 => array('AS', 'Athena sftp'),
														-9 => array('E', 'Ellkay'),
														-10 => array('WH', 'Wayne Health'),
														-11 => array('EX', 'Experity'),
														-12 => array('PC', 'Prefill Covid'),
														-13 => array('VA', 'VitalAxis'),
														-14 => array('CE', 'Centaur'),
														-15 => array('DI', 'Dimension'),
														-16 => array('PH', 'Phoresis'),
													);
													$apiarr = $apis[$row->paid];
													if ($apiarr) {
														echo '<span class="paidicon" title="' . $apiarr[1] . '">' . $apiarr[0] . '</span>';
													}
												} else if ($row->paid == -1) {
													echo '<span class="insurance_icon"></span>';
												} else if ($row->paid == 3 || $row->paid == 4) {
													echo '<span class="paidicon ' . ($row->paid == 4 ? 'red' : 'green') . '" title="Pay at Lab">$</span>';
												} else if ($row->paid == 1 || $row->paid == 2) {
													echo '<span class="checkmark ' . ($row->paid == 2 ? 'red' : 'green') . '" title="Paid"></span>';
												}
												?></td>
						<?php } ?>
						<?php
						if (in_array($tab, $list_tab_show_qc)) {
							if (tr_get_option('ci_pdf_data', 'inb_showmf_fa')) {
								$has_fa = in_array($mf_fa_groups['fa'], $row_group_ids);
								$has_mf = in_array($mf_fa_groups['mf'], $row_group_ids);
						?>
								<td>
									<?php if ($has_fa) { ?>
										<input type="checkbox" class="qc_cb" data-type="blood_fa" <?php if ($tab == 'batch000') echo 'disabled' ?> <?php checked($row->blood_fa) ?> />
									<?php } ?>
								</td>
								<td>
									<?php if ($has_mf) { ?>
										<input type="checkbox" class="qc_cb" data-type="blood_mf" <?php if ($tab == 'batch000') echo 'disabled' ?> <?php checked($row->blood_mf) ?> />
									<?php } ?>
								</td>
							<?php
							} else {
							?>
								<td><input type="checkbox" class="qc_cb" data-type="qc" <?php checked($row->qc) ?> /></td>
						<?php
							}
						}
						?>
						<?php if ($mv_bref_top) {
							$tbl_batch_meta = tr_pdd_get_table_by_btype($row->btype) . '_meta';
							$_mid = isset(explode('_', $row->mid)[1]) ? explode('_', $row->mid)[1] : $row->mid;
							$hold_status = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$tbl_batch_meta} WHERE mid = %d AND meta_key = 'hold_batch_status' LIMIT 1", $_mid)); ?>
							<td><?php echo $row->lab_ref ?></td>
							<td class="batch_status" data-status="<?php echo $row->batch_status ?>" data-btype="<?php echo $row->btype; ?>" data-hold-status="<?php echo $hold_status; ?>"></td>
						<?php } ?>
						<?php if ($inb_rv_mp) {
						?>
							<td><?php echo $panel ?></td>
						<?php
						} else if (!$hide_col_medication) {
						?>
							<td style="max-width: 150px" class="col-current_medication"><?php echo $medications ?></td>
						<?php
						} ?>
						<?php if ($inb_ccbdob) echo '<td>' . $row->cli_city . '</td>' ?>
						<td class="col-dob"><?php echo $row->pa_dob ?></td>
						<td><?php echo $row->requesting_practice ?></td>
						<?php if (!$inb_ccbdob) echo '<td>' . $row->cli_city . '</td>' ?>
						<?php if ($inb_ssn) echo '<td>' . $row->pa_ssn . '</td>' ?>
						<td><?php echo $row->requesting_physician ?></td>
						<td><?php echo $row->physician_phone ?></td>
						<td><?php echo $row->insurance ?></td>
						<?php if ($inb_insmid) echo '<td>' . $row->ins_member_id . '</td>' ?>
						<?php if ($inb_insgid) echo '<td>' . $row->ins_group_id . '</td>' ?>
						<td><?php echo $row->spec_received ?></td>
						<td><?php echo $row->lab ?></td>
						<?php if (!$mv_bref_top) {
							$tbl_batch_meta = tr_pdd_get_table_by_btype($row->btype) . '_meta';
							$_mid = isset(explode('_', $row->mid)[1]) ? explode('_', $row->mid)[1] : $row->mid;
							$hold_status = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$tbl_batch_meta} WHERE mid = %d AND meta_key = 'hold_batch_status' LIMIT 1", $_mid));
						?>
							<td><?php echo $row->lab_ref ?></td>
							<td class="batch_status" data-status="<?php echo $row->batch_status ?>" data-btype="<?php echo $row->btype; ?>" data-hold-status="<?php echo $hold_status; ?>"></td>
						<?php } ?>
						<?php if (!$inb_rv_mp) {
						?>
							<td><?php echo $panel ?></td>
						<?php
						} else if (!$hide_col_medication) {
						?>
							<td style="max-width: 150px" class="col-current_medication"><?php echo $medications ?></td>
						<?php
						} ?>
						<td><?php echo $row->type_of_specimen ?></td>
						<?php if (!tr_get_option('ci_pdf_data', 'hide_ib_notes')) { ?>
							<td><?php echo nl2br($row->notes) ?></td>
						<?php } ?>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>

	<div class="tablenav bottom">
		<?php if ($tab == 'batches' || $tab == 'current_batches' || $tab == 'batch000') { ?>
			<?php
			if (count($access_btypes) > 1) {
				echo '<div class="tips left" style="line-height:30px">';
				foreach ($access_btypes as $bty) {
					if ($bty == 'study') continue;
					$color = isset($btype_colors[$bty]) ? $btype_colors[$bty] : '';
					echo ' [ <a class="btcolor" href="' . Tr_Base_Class_V6::link(array('qbtype' => $bty), false) . '" style="color:' . $color . '">' . tr_pdd_get_typename($bty) . '</a> ] ';
				}
				echo '</div>';
			}
			?>
		<?php } ?>
		<?php echo $pagination->pagination() ?>
	</div>
	<style>
		table.tblarchived thead tr th {
			background: #d95b0b !important;
		}
	</style>
	<script>
		jQuery(function($) {
			var $inbound_statuses = <?php echo json_encode($b_statuses) ?>;
			var $disable_statuses = <?php echo json_encode($disable_statuses) ?>;
			var is_new_batch_status = <?php echo json_encode($is_new_batch_status) ?>;
			$('.batch_status').each(function() {
				const status = $(this).data('status');
				const holdStatus = $(this).data('hold-status');

				let statuses = $inbound_statuses[$(this).data('btype')] ?? {};
				let disable_options = $disable_statuses[$(this).data('btype')] ?? [];
				if (status === 'hold' && is_new_batch_status) {
					if (holdStatus) {
						statuses = {
							'hold': 'On hold',
							[holdStatus]: statuses[holdStatus] ?? '',
						}
					}
				}

				var $html = '<select data-id="' + $(this).parent().data('mid') + '"  class="njs_select2">';
				for (k in statuses) {
					const isDisable = disable_options.includes(k);
					const disableAttr = isDisable && status !== 'hold' ? 'disabled ' : ''
					$html += '<option value="' + k + '" ' + disableAttr + (k == $(this).data('status') ? 'selected' : '') + '>' + statuses[k] + '</option>';
				}
				$html += '</select>';
				$(this).html($html);
			});
			$('.batch_status select').change(function() {
				var $this = $(this);
				var $vl = $(this).val();
				var $id = $(this).data('id');
				var $pr = $(this).parents('tr');
				$.ajax({
					type: 'post',
					dataType: 'json',
					data: {
						tr_action: 'ci_change_batch_status',
						id: $id,
						status: $vl
					},
					success: function(rs) {
						$pr.find('.sloading').remove();
						if (rs.status == 'error') {
							alert(rs.msg);
							$this.val(rs.old_status).trigger('change.select2');
						}
					},
					error: function() {
						$this.change();
					}
				})
			});
			$('a.editinline').click(function() {
				var tr = $(this).parents('tr');
				var mid = tr.data('mid');
				var $col = tr.find('td').length;
				var $fields = '';
				var $actions = '<p class="submit inline-edit-save"><a class="button-secondary cancel" href="#inline-edit">Cancel</a> <a class="button-primary save" href="#inline-edit">Update</a><br class="clear"></p>';
				var $new = $('<tr id="edit-' + mid + '" class="no_hover inline-edit-row inline-edit-row-post inline-edit-post quick-edit-row quick-edit-row-post inline-edit-post inline-editor"><td class="colspanchange" colspan="' + $col + '"><form style="max-width:1000px">' + $fields + '</form>' + $actions + '</td></tr>');
				$new.data('mid', mid);
				$('form', $new).append($('.editinline_data .inline_data', tr));
				$new.find('.cancel').click(function() {
					$('.editinline_data', tr).append($('form .inline_data', $new));
					tr.show();
					$new.remove();
					return false;
				});
				$new.find('.save').click(function() {
					var $dt = $new.find('form').serialize();
					var mid = $new.data('mid');
					$.ajax({
						url: ajaxurl + '?tr_action=save_quick_edit_batch&action=ajax&mid=' + mid,
						type: 'post',
						data: $dt,
						dataType: 'json',
						success: function(rs) {
							if (rs.status == 'ok') {
								for (i in rs.cols) {
									$('.col-' + i, tr).html(rs.cols[i]);
								}
								$('.editinline_data', tr).append($('form .inline_data', $new));

								tr.show();
								$new.remove();
							} else {
								alert('Error. Please try again');
							}

						}
					});
					return false;
				})
				tr.after($new);
				tr.hide();
				$new.find('.hasdate2').each($.tr_setup_datef);
				return false;
			});
			$('a.delete').click(function() {
				if (confirm('Are you sure you want to delete this entry?') == false) return false;
				if ($(this).data('href')) {
					$(this).attr('href', $(this).data('href'));
				}
			})
			$('#allstatus').click(function() {
				$(this).parents('form').submit();
			})

			$('body').on('click', '.mashcol', function() {
					var $ctype = $(this).data('type');
					var $checked = $(this).is(':checked');
					$('input.qc_cb[data-type="' + $ctype + '"]').prop('checked', $checked).change();
				})
				.on('change', '.qc_cb', function() {
					var $mid = $(this).parents('tr').data('mid');
					var $checked = $(this).is(':checked') ? 1 : 0;
					var $ctype = $(this).data('type');
					$.ajax({
						type: 'post',
						dataType: 'json',
						data: {
							trajax: 1,
							act: 'change_qc',
							ctype: $ctype,
							mid: $mid,
							checked: $checked
						},
						success: function(json) {

						}
					})
				})
			$('.tips a').click(function() {
				$('body,html').scrollTop($($(this).attr('href')).offset().top - 110);
				return false;
			})

		})
	</script>
<?php
}
