<?php
global $wpdb, $tr_sms_gateway;

if (isset($_REQUEST['sms_result'])) {
    trcppr_cron_notify_sms();
}

if ($act == 'resend') {
    $return = array('success' => 0);
    $slugkey = $_REQUEST['slugkey'];
    list($phone, $created, $sendtype) = explode('_', $slugkey);
    $sql = "select * from tr_log_sms 
            where phone = '{$phone}'
                and created ='{$created}'
                and sendtype = '{$sendtype}' ";
    $row = $wpdb->get_row($sql);

    if ($row) {
        $btype = '';
        $report_id = 0;

        foreach (tr_pdd_get_batch_types() as $btype) {
            $report_tbl = tr_pdd_get_table_report_by_btype($btype);
            $report_id = $wpdb->get_var(
                "select re.id from {$report_tbl} re
                                where re.patient_id = '{$row->assen}'
                                "
            );
            if ($report_id > 0) {
                break;
            }
        }

        $loaded = $tr_sms_gateway->load_library();

        if ($loaded) {
            $init = $tr_sms_gateway->init_api();
        }

        if ($row->sendtype == 'nt') {
            $sent = $tr_sms_gateway->send_sms($row->phone, $row->message, $row->patient_name, $row->assen, $row->sendtype, $row->patient_id);
        } else if ($row->sendtype == 'ms' && $report_id > 0) {
            $url = tr_patient_qr_url_report($report_id, $btype);
            $sent = $tr_sms_gateway->send_sms($row->phone, $row->message, $row->patient_name, $row->assen, $row->sendtype, $row->patient_id, array($url));
        }

        $return['sendtype'] = $row->sendtype;

        if ($sent) {
            $return['success'] = 1;
        }
    }

    wp_send_json($return);
    exit;
}

wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui-datepicker', TRCPPR_URL . 'css/jquery-ui-datepicker.css', array(), '1.8');

$settingsmain = get_blog_option(BLOG_ID_CURRENT_SITE, 'ci_pdf_data', array());
$keyassen = $settingsmain['barcode_key'];

$stypes = array(
    'n' => 'Welcome',
    'v' => 'Verify Code',
    'nt' => 'Results',
    'ms' => 'MMS',
    'f' => 'Failed',
);

$where = '';
$join_total = '';
if (!empty($_REQUEST['patient'])) {
    $where .= " and patient_name like '%{$_REQUEST['patient']}%' ";
}

if (!empty($_REQUEST['assen'])) {
    $where .= " and (assen like '%{$_REQUEST['assen']}%' 
	                
	                 )";
    //or (assen = '' and sendtype in ('nt','ms') and message like '%{$_REQUEST['assen']}%')
}
if (!empty($_REQUEST['phone'])) {
    $phone = trim($_REQUEST['phone']);
    $phone = str_replace(array('+1', '-', ' ', '.', '(', ')'), '', $phone);
    $phones = array($phone);
    $phones[] = '+1' . $phone;
    $phones = implode("','", $phones);
    $where .= " and phone in ('{$phones}') ";
}

$dateopt = isset($_GET['dateopt']) ? $_GET['dateopt'] : '30';
if ($dateopt == '30') {
    $where .= " and sms.created >= '" . date('Y-m-d', strtotime('-30 days')) . "' ";
} else if ($dateopt == '90') {
    $where .= " and sms.created >= '" . date('Y-m-d', strtotime('-90 days')) . "' ";
} else if ($dateopt == '1yr') {
    $where .= " and sms.created >= '" . date('Y-m-d', strtotime('-1 year')) . "' ";
} else if ($dateopt == '0') {
    $where .= " and sms.created >= '2000-01-01' ";
}

if (!empty($_GET['datefrom'])) {
    $datefrom = date('Y-m-d', strtotime($_GET['datefrom']));
    $where .= " and sms.created >= '{$datefrom}' ";
    if (empty($_GET['dateto'])) {
        $_GET['dateto'] = $_GET['datefrom'];
    }
}
if (!empty($_GET['dateto'])) {
    $dateto = date('Y-m-d', strtotime($_GET['dateto']));
    $where .= " and sms.created <= '{$dateto} 23:59:59' ";
}

if (isset($_GET['cid']) && $_GET['cid'] > 0) {
    $cid = $_GET['cid'];
    $where .= " and pa.pa_clinic_ID = {$cid} ";

    $join_total .= "left join {$wpdb->usermeta} ump ON ump.meta_key='phone' 
                                        and ump.meta_value=sms.phone
	 left join {$wpdb->users} u ON u.ID = ump.user_id
	 left join ci_patients pa ON pa.pa_ID = u.patient_id or pa.pa_ID = sms.patient_id
	             ";
}




if (isset($_GET['stype']) && !empty($_GET['stype'])) {
    $stype = $_GET['stype'];
    if ($stype == 'n') {
        $stype = "n','no";
    } else if ($stype == 'nt') {
        $stype = "nt','ms";
    }
    $where .= " and sms.sendtype in ('{$stype}') ";
} else {
    $where .= " and sms.sendtype not in ('f') ";
}

$order_by = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'created';
$order = isset($_REQUEST['order']) ? $_REQUEST['order'] : 'desc';
$order_by = "order by {$order_by} {$order}";


$row_per_page = 30;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();

$sql_total = "select count(*) 
                    from tr_log_sms sms 
                    {$join_total}
                    where 1 {$where}
                    ";


if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

    $cache = new Tr_Cache_Query('log_sms');
    $total = $cache->get_var($sql_total, 0, 0, 3600);
} else {
    $total = $wpdb->get_var($sql_total);
}

$pagination->setTotal($total);
$sql = "
	select 
	 sms.*,
	 pa.pa_ID,
	 pa.pa_first,
	 pa.pa_last
	 from tr_log_sms sms
	 left join ci_patients pa ON  pa.pa_ID = sms.patient_id     
	 where 1 {$where}
	{$order_by}";

//left join {$wpdb->usermeta} ump ON ump.meta_key='phone' 
//and ump.meta_value=sms.phone
//left join {$wpdb->users} u ON u.ID = ump.user_id
//pa.pa_ID = u.patient_id or

if (empty($act) || $act != 'export') {
    $sql .= " limit {$offset},{$row_per_page} ";
}

//var_dump($sql);exit;

$rows = $wpdb->get_results($sql);

if ($act == 'export') {
    $filename = 'export.csv';
    header("Content-disposition: attachment; filename={$filename}");
    header("Content-Type: text/csv");

    $f = fopen('php://output', 'w');
    $headers = array('Date/Time', 'Patient Name', 'Phone Number', 'Accession', 'Scheduled', 'Message');
    fputcsv($f, $headers);

    $duplicates = array();
    foreach ($rows as $row) {
        if (empty($row->pa_last)) {
            $phones = implode("','", tr_patient_get_phone_arr($row->phone));

            $patient = $wpdb->get_row("select pa_first,pa_last from ci_patients 
					            where pa_phone in ('{$phones}')");
            if ($patient) {
                $row->pa_last = $patient->pa_last;
                $row->pa_first = $patient->pa_first;
            }
        }



        preg_match_all("/([a-zA-Z]{3} [0-9]+ @ [0-9]+:[0-9]+[A|P|M]+)/", $row->message, $matches_scheduled);

        if (empty($row->assen)) {
            preg_match_all("/({$keyassen}[0-9]+)/", $row->message, $matches);
            $row->assen = implode(', ', $matches[1]);
        }

        if (empty($row->patient_name)) {
            $row->patient_name = $row->pa_last . ', ' . $row->pa_first;
            $wpdb->update('tr_log_sms', array(
                'patient_name' => $row->patient_name,
                'assen' => $row->assen
            ), array(
                'phone' => $row->phone,
                'created' => $row->created
            ));
        }
        $key_duplicate = $row->phone . '_' . $row->created . '_' . $row->sendtype . '_' . $row->assen;
        if (in_array($key_duplicate, $duplicates)) {
            continue;
        }
        $duplicates[] = $key_duplicate;

        $data = array(
            $row->created,
            $row->patient_name,
            $row->phone,
            $row->assen,
            implode(', ', $matches_scheduled[1]),
            $row->message,
        );
        fputcsv($f, $data);
    }
    exit;
}
if (isset($_GET['debug'])) {
    var_dump($sql);
}

$clinics = $wpdb->get_results("select cID, cli_clinic from ci_clinic where cli_status = 1 order by cli_clinic asc");
?>

<div class="wrap">
    <h2>Log Sms</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="get" class="formsearch">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?? '' ?>" />
        <label>Patient: </label>
        <input type="search" name="patient" value="<?php echo $_GET['patient'] ?? '' ?>" />
        <label>Accession: </label>
        <input type="search" name="assen" value="<?php echo $_GET['assen'] ?? '' ?>" />
        <label>Phone: </label>
        <input type="search" name="phone" value="<?php echo $_GET['phone'] ?? '' ?>" />

        <br>
        <label>&nbsp;</label>
        <select name="dateopt">
            <?php
            $dateopts = array(
                '30' => 'Past 30 Days',
                '90' => 'Past 90 Days',
                '1yr' => 'Past year',
                '0' => 'All Time'
            );
            foreach ($dateopts as $k => $vl) {
                $selected = $k == $dateopt ? 'selected' : '';
                echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
            }
            ?>
        </select>
        <label>Clinic:</label>
        <select name="cid">
            <option value="">Select</option>
            <?php
            foreach ($clinics as $clinic) {
                $selected = $clinic->cID == ($_GET['cid'] ?? '') ? 'selected' : '';
                echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
            }
            ?>
        </select>
        <label>Type:</label>
        <select name="stype">
            <option value="">All Type</option>
            <?php
            foreach ($stypes as $k => $vl) {
                $selected = $k == ($_GET['stype'] ?? '') ? 'selected' : '';
                echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
            }
            ?>
        </select>

        <span style="display: inline-block;padding-left:70px">
            <input type="submit" class="button-primary" value="Search" />
            <input type="button" class="button export_btn" value="Export" />
        </span>
    </form>
    <div class="tablenav">
        <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
    </div>

    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
            <thead>
                <tr>
                    <?php tr_tbl_sort('created', 'Date/Time') ?>
                    <?php tr_tbl_sort('patient_name', 'Patient Name') ?>
                    <?php tr_tbl_sort('phone', 'Phone Number') ?>
                    <th style="width: 100px">Accession</th>
                    <th style="width: 100px">scheduled</th>
                    <th>Text</th>
                    <td style="width:80px"></td>
                </tr>
            </thead>

            <tbody>
                <?php
                $duplicates = array();
                if (count($rows) > 0) {
                    foreach ($rows as $row) {
                        if (empty($row->pa_last)) {
                            $phones = implode("','", tr_patient_get_phone_arr($row->phone));
                            $patient = null;
                            if (!empty($phones)) {
                                $patient = $wpdb->get_row("select pa_first,pa_last from ci_patients 
                            where pa_phone in ('{$phones}')");
                            }


                            if ($patient) {
                                $row->pa_last = $patient->pa_last;
                                $row->pa_first = $patient->pa_first;
                            }
                        }



                        preg_match_all("/([a-zA-Z]{3} [0-9]+ @ [0-9]+:[0-9]+[A|P|M]+)/", $row->message, $matches_scheduled);

                        if (empty($row->assen)) {
                            preg_match_all("/({$keyassen}[0-9]+)/", $row->message, $matches);
                            $row->assen = implode(', ', $matches[1]);
                        }

                        if (empty($row->patient_name)) {
                            $row->patient_name = $row->pa_last . ', ' . $row->pa_first;
                            $wpdb->update('tr_log_sms', array(
                                'patient_name' => $row->patient_name,
                                'assen' => $row->assen
                            ), array(
                                'phone' => $row->phone,
                                'created' => $row->created
                            ));
                        }
                        $key_duplicate = $row->phone . '_' . $row->created . '_' . $row->sendtype . '_' . $row->assen;
                        if (in_array($key_duplicate, $duplicates)) {
                            continue;
                        }
                        $duplicates[] = $key_duplicate;
                ?>
                        <tr class="childp">
                            <td><?php echo $row->created ?></td>
                            <td title="<?php echo $row->pa_ID ?>"><?php echo $row->patient_name ?></td>
                            <td><?php echo $row->phone ?></td>
                            <td><?php echo $row->assen ?></td>
                            <td><?php echo implode(', ', $matches_scheduled[1]) ?></td>
                            <td><?php echo $row->message ?></td>
                            <td>
                                <?php if (in_array($row->sendtype, array('nt', 'ms', 'n', 'no'))) {
                                ?>
                                    <a class="resend button" data-slugkey="<?php echo $key_duplicate ?>">Resend</a>
                                <?php
                                } ?>
                            </td>
                        </tr>
                    <?php
                    }
                } else {
                    ?>
                    <tr class="childp">
                        <td colspan="6">Not found</td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
</div>
<style>
    .formsearch label {
        display: inline-block;
        text-align: right;
        padding-right: 5px;
        width: 60px;
    }

    .formsearch input[type="search"],
    .formsearch select {
        width: 200px;
    }
</style>

<script>
    jQuery(document).ready(function($) {

        $('body').on('click', '.export_btn', function() {
            var $url = 'admin.php?act=export&trajax=1&' + $('form.formsearch').serialize();
            $('body iframe.export').remove();
            $('body').append('<iframe class="export" style="position: absolute;left:-1110px;width:1px;height: 1px;" src="' + $url + '"></iframe>');
        }).on('click', 'a.resend', function() {
            var $this = $(this);
            if ($this.html() == 'Sending...') {
                return false;
            }
            if (confirm('Do you want resend?')) {
                $this.data('oldtext', $this.html());
                $this.html('Sending...');
                var $slugkey = $this.data('slugkey');
                $.ajax({
                    data: {
                        trajax: 1,
                        act: 'resend',
                        slugkey: $slugkey
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function(rs) {
                        $this.html($this.data('oldtext'));
                        if (rs.success) {
                            alert('Sent Successfully!');
                            $this.hide();
                        } else {
                            alert('Error. Please try again!');
                        }
                    },
                    error: function() {
                        $this.html($this.data('oldtext'));
                        alert('Error. Please try again!');
                    }
                });
            }
            return false;
        })

        $('.hasdate').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    })
</script>