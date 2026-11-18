<?php
global $wpdb;

return include(trpdd_get_file_path('abx', 'new_report'));

$cache_dir = WP_CONTENT_DIR . '/cache/abx/';
if (!file_exists($cache_dir)) {
    wp_mkdir_p($cache_dir);
}
wp_enqueue_style('admin-abx', TRCPDD_URL . 'css/admin_abx.css', array(), TRCPDD_VS);
wp_enqueue_script('html2canvas', TRCPDD_URL . 'js/html2canvas.min.js', array(), TRCPDD_VS);

$monthnow = date_i18n('m');
$yearnow = date_i18n('Y');
$month6 = $monthnow - 5;
if ($month6 <= 0) {
    $yearnow -= 1;
    $month6 += 12;
}
$date_from_6months = $yearnow . '-' . $month6 . '-01';


$main_abx_group_gids = array();

$main_groups = $wpdb->get_results("
select tg_ID, tg_name, tg_other_options
from ci_test_groups
where tg_parent = 0
and tg_type = 'pcr'

and tg_other_options like '%new_abx\":\"1%'
and tg_antibiotic = 0 
order by tg_position asc
"); //and tg_show_req = 1


$tabs = array();
if (count($main_groups) == 0) {
    $main_abx_group_gids = $wpdb->get_col(
        "select tg_ID from ci_test_groups
    where tg_name like '%uti%'
    and tg_parent = 0
    and tg_type = 'pcr'
    and tg_show_req = 1"
    );
} else if (count($main_groups) > 0) {


    foreach ($main_groups as $g) {
        $tabtext = 'UTI';
        $tabkey = 'uti';
        $abx_actived = true;
        $other_options = json_decode($g->tg_other_options, true);

        if (isset($other_options['abx_type']) && !empty($other_options['abx_type'])) {
            $tabkey = $other_options['abx_type'];
            $tabtext = strlen($g->tg_name) < 7 || strlen($tabkey) <= 2 ? $g->tg_name : strtoupper($tabkey);
            if ($tabkey == 'v') {
                $tabtext = 'Vaginitis';
            } else if ($tabkey == 'gi') {
                $tabtext = 'GI';
            }

            if (isset($other_options['new_abx_date']) && $other_options['new_abx_date'] > date_i18n('Y-m-d')) {
                //  $abx_actived = false;
            }
        } else if (stripos($g->tg_name, 'wound') !== false) {
            $tabkey = 'wound';
            $tabtext = 'Wound';
        } else if (stripos($g->tg_name, 'gi') !== false) {
            $tabkey = 'gi';
            $tabtext = 'GI';
        } else if (stripos($g->tg_name, 'Respiratory') !== false || stripos($g->tg_name, 'rpp') !== false) {
            $tabkey = 'r';
            $tabtext = 'Respiratory';
        }


        if (in_array($tabkey, array('sm', 'sr', 'sp', 'sl', 'es'))) {
            $tabkey = 'sti';
        }


        if ($tabkey == 'sti') {
            $tabtext = 'STI';
        }

        $tabtext = trim(str_ireplace(array('(new)', ' panel'), '', $tabtext));

        if (!isset($tabs[$tabkey])) {
            $tabs[$tabkey] = array(
                'title' => $tabtext,
                'actived' => $abx_actived,
                'groupids' => array()
            );
        }

        $tabs[$tabkey]['groupids'][] = $g->tg_ID;
        $tabs[$tabkey]['actived'] = $abx_actived;
    }
}

if (tr_get_option('ci_pdf_data', 'new_abx_contact')) {
    $tabs['uyr'] = array('title' => 'Understanding your report');
    $tabs['review'] = array('title' => 'Request ABX review');
    $tabs['contact'] = array('title' => 'Contact Us');
}

$tab        = !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
$tab_title  = isset($tabs[$tab]['title']) ? $tabs[$tab]['title'] : 'UTI';

if (count($main_groups) > 0 && !empty($tabs) && isset($tabs[$tab])) {
    $main_abx_group_gids = $tabs[$tab]['groupids'];
}

if (isset($_GET['debug'])) {
    var_dump($main_groups);
    echo "<br><br>";
    var_dump($tabs);
}

$show_clinic    = false;
$report_object  = '';
$conditions     = trpdd_get_batch_conditions(0, 'pcr');
if (isset($_GET['debug1'])) {
    var_dump($conditions);
}

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'create_pdf') {
    include('create_pdf.php');
    exit;
}

$where_group = array();
if (!in_array($tab, array('uyr', 'review', 'contact'))) {
    if (strlen($conditions['where_clinic']) > 10) {

        $sql = "select * from ci_clinic where {$conditions['where_clinic']} order by cli_clinic asc";

        $clinics = $wpdb->get_results($sql);

        if (isset($_GET['debug1'])) {
            var_dump($sql);
        }
    } else {
        $report_object = CI_Base_Lab::getCurrentLabName();


        foreach ($main_abx_group_gids as $gid) {
            $where_group[] = " FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)  ";
        }
        $where_group = implode(' or ', $where_group);


        $clinics = $wpdb->get_results("select * , 
                        (select count(*) from ci_batches_pcr ba
                        where cli_ID = cID
                        and ({$where_group})
                        and is_saved = 0
                        and doc >= '{$date_from_6months}') as count  
        from ci_clinic where cli_status = 1 order by cli_clinic asc");


        usort($clinics, function ($a, $b) {
            return $b->count  - $a->count;
        });
    }

    if (count($clinics) > 1) {
        $show_clinic = true;


        if (isset($_GET['cid']) && $_GET['cid'] > 0) {

            $conditions['where'] .= ' and ba.cli_ID = ' . $_GET['cid'];


            foreach ($clinics as $clinic) {
                if ($clinic->cID == $_GET['cid']) {
                    $report_object = $clinic->cli_clinic;
                    break;
                }
            }
        } else if (empty($report_object)) {
            $report_object = 'All Offices';
        }
    } else {
        foreach ($clinics as $clinic) {
            $report_object = $clinic->cli_clinic;
            break;
        }
    }
}


?>


<?php
if (!isset($_REQUEST['trajax'])) {
    echo '<div class="wrap">';
    if (count($tabs) > 1) {


        echo '<h2 class="nav-tab-wrapper dontprint">';
        foreach ($tabs as $k => $arr) {
            $class = $tab ==  $k ? 'nav-tab-active' : '';
            $link  = Tr_Base_Class_V6::link(array(
                'tab'         => $k,
                'cid'         => '',
                'm'         => '',
            ), false);
            echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $arr['title'] . '</a>';
        }
        echo '</h2>';
        echo '<div class="clear"></div>';
    }
    echo '</div>';
}

if (in_array($tab, array('uyr', 'review', 'contact'))) {
    include($tab . '.php');
} else {
    $abx_key = 0; //unk
    switch ($tab) {
        case 'uti':
            $abx_key = 1;
            break;
        case 'wound':
            $abx_key = 2;
            break;
        case 'gi':
            $abx_key = 3;
            break;
        case 'r':
            $abx_key = 4;
            break;
        case 'v':
            $abx_key = 5;
            break;
    }
?>
    <div class="wrap">
        <h2 class="dontprint">
            igniteABX Reports
            &nbsp;&nbsp;
            <?php
            if ($show_clinic) {
                echo '<select name="cid">';
                echo '<option value="">All Offices</option>';
                foreach ($clinics as $clinic) {
                    $selected = isset($_GET['cid']) && $clinic->cID == $_GET['cid'] ? 'selected' : '';
                    if (isset($clinic->count) && $clinic->count > 0) {
                        $clinic->cli_clinic .= ' (' . $clinic->count . ')';
                    }

                    echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
                }
                echo '</select>';
            }
            ?>
            <select id="main_month">
                <?php
                $from_date = date_i18n('Y-m-01');
                for ($i = 0; $i <= 5; $i++) {
                    $mfrom  = date('Y-m-01', strtotime('-' . $i . ' months', strtotime($from_date)));
                    $monthname  = date('M', strtotime($mfrom));
                    echo '<option value="' . $monthname . '" data-my="' . date('M_Y', strtotime($from_date)) . '">' . $monthname . '</option>';
                }
                ?>
            </select>

            <a class="button print_widgets">Print</a>

            <a class="button pdf_widgets2">PDF</a>
        </h2>

        <div id="dashboard-widgets-wrap" class="abxreport">
            <div id="dashboard-widgets" class="metabox-holder">
                <div class="postbox-container">

                    <?php
                    include('report_number_tests.php');

                    include('report_bacteria.php');
                    echo '<div class="break"></div>';

                    if ($tab != 'v') {
                        include('report_marker.php');

                        include('report_polymicrobial_infections.php');

                        include('report_mdr_infections.php');
                    }
                    ?>

                </div>
            </div>
        </div>
    </div>

    <style>
        .montname {
            display: inline !important;
        }

        .montname::before {
            display: none !important;
        }

        @media print {
            @page {
                margin: 0;
            }

            body {
                zoom: 66%;
                margin: 1cm 0.5cm;
            }

            body * {

                -webkit-print-color-adjust: exact;
            }


            #dashboard-widgets .postbox-container .postbox_wrap {
                padding: 5px 10px;
            }

            #wpbody-content .metabox-holder,
            .wrap,
            #dashboard-widgets-wrap,
            html.wp-toolbar,
            #wpcontent {
                padding: 0 !important;
                margin: 0 !important;
                overflow: inherit !important;
            }

            h2,
            h3 {
                margin: 8px 0;
            }


        }

        .break {
            page-break-before: always;
            margin-bottom: 1cm;
        }

        .printing #wpbody-content .metabox-holder,
        .printing .wrap,
        .printing #dashboard-widgets-wrap,
        .printing #wpcontent {
            padding: 0 !important;
            margin: 0 !important;
            overflow: inherit !important;
        }

        .printing #wpbody-content {
            width: 1200px !important;
        }

        .centerchar {
            margin: 0 auto;
        }
    </style>
    </div>


    <script>
        function addScript(url) {
            var script = document.createElement('script');
            script.type = 'application/javascript';
            script.src = url;
            document.head.appendChild(script);
        }
        addScript('https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js');
        jQuery(function($) {
            $('body').on('click', '.print_widgets', function() {
                window.print();
                return false;
            }).on('click', '.pdf_widgets', function() {
                var $this = $(this);
                if ($this.html() == 'Loading...') {
                    return false;
                }

                $this.data('oldhtml', $this.html());
                $this.html('Loading...');
                html2canvas(document.querySelector("#dashboard-widgets-wrap"), {
                    scale: 2
                }).then(canvas => {
                    var canvasData = canvas.toDataURL("image/png");

                    $('#downloadpdf').remove();
                    $('#downloadpdf_form').remove();
                    $('body').append('<iframe style="position:absolute;left:-999999px;" id="downloadpdf" name="downloadpdf"></iframe>');
                    var $f = $('<form method="post" target="downloadpdf" id="downloadpdf_form"><input type="hidden" name="trajax" value="create_pdf"/>' +
                        '<input type="hidden" name="imagedata" value="' + canvasData + '"/></form> ');
                    $('body').append($f);
                    $f.submit();
                });
                setTimeout(function() {
                    $this.html($this.data('oldhtml'));
                }, 3000);
                return false;
            }).on('click', '.pdf_widgets2', function() {
                var $this = $(this);
                if ($this.html() == 'Loading...') {
                    return false;
                }

                $this.data('oldhtml', $this.html());
                $this.html('Loading...');
                $('body').addClass('printing');
                $('select#main_month').change();
                var $monthyear = $('#main_month option:selected').data('my');
                var element = document.getElementById('wpbody-content');
                var opt = {
                    margin: [0, 10, 0, 10],
                    filename: '<?php echo $tab_title . '_Statistics_Report_' . $report_object . '_' ?>' + $monthyear + '.pdf',
                    image: {
                        type: 'jpeg',
                        quality: 0.98
                    },
                    pagebreak: {
                        avoid: ".break",
                        mode: "css"
                    },
                    html2canvas: {
                        scale: 2,
                        dpi: 300,
                        x: 0,
                        y: 0,
                        scrollX: 0,
                        width: 1200,
                        windowWidth: 800,
                        ignoreElements: function(element) {
                            if (element.classList.contains('dontprint')) {
                                return true;
                            }

                        }
                    },
                    jsPDF: {
                        unit: 'pt',
                        format: 'letter',
                        orientation: 'portrait'
                    }
                };

                html2pdf().set(opt).from(element).save();

                setTimeout(function() {
                    $('body').removeClass('printing');
                }, 1000);

                setTimeout(function() {
                    $this.html($this.data('oldhtml'));
                }, 3000);
                return false;
            }).on('change', 'select#main_month', function() {
                $('.selectmonth:not(.buildedselect)').addClass("nofocus").click().removeClass("nofocus");
                $('.selectmonth select:not(.nochange)').addClass('nochange').val($(this).val()).click().change().removeClass('nochange');
                $('.selectmonth select.nochange').removeClass('nochange');
                $('.montname').html($(this).val());
                $('.bymonth').hide();
                $('.bymonth_' + $(this).val()).show();
            }).on('change', '.selectmonth select', function() {
                if (!$(this).hasClass('nochange')) {
                    $(this).addClass('nochange');
                    $('select#main_month').val($(this).val()).change();
                }

            }).on('change', 'select[name="cid"]', function() {
                location.href = '<?php Tr_Base_Class_V6::link(array('cid' => '')) ?>&cid=' + $(this).val();
            })
        })
    </script>
<?php } ?>