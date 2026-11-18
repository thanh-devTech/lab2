<?php
global $wpdb;

global $current_role;
global $blog_id;
global $report;
global $ci_all_btypes;
global $access_btypes;


include_once(TRCPDD_PATH.'pages/formdata.php');

$today          = date_i18n('Y-m-d');
$patient        = isset($_GET['patient'])? $_GET['patient']:'';
$patient_mrn    = isset($_GET['patient_mrn'])? $_GET['patient_mrn']:'';
$patient_id     = isset($_GET['patient_id'])? $_GET['patient_id']:'';
$from_date      = !empty($_GET['from'])? $_GET['from']:date('Y-m-d', strtotime($today)-86400*365);
$to_date        = !empty($_GET['to'])? $_GET['to']:$today;
$selected_panelid   = !empty($_GET['panelid'])? $_GET['panelid']:0;
$summary        = !empty($_GET['summary'])? $_GET['summary']:0;
$user_id        = get_current_user_id();

if($summary)
{
	$patient_id     = 0;
	$patient        = '';
	$selected_panelid = 0;

	//check date range
	if(strtotime($to_date) - strtotime($from_date)> 86400*91)
	{
		Tr_Session_Class::add_error('Summary Limit 3 months');
		$from_date = date('Y-m-d', strtotime($to_date)-86400*90);
	}
}

$patientobj = null;

if(!empty($patient_mrn))
{
	$patientobj = $wpdb->get_row("select * from ci_patients where pa_mrn='{$patient_mrn}'");
	if($patientobj)
	{
		$patient_id = $patientobj->pa_ID;
		$patient = '';
	}else{
		$patient_mrn = '';
	}
}
//find patient
if(!empty($patient) && $patient_id == 0)
{
	$pa_first = $pa_last = '';
	if(stripos($patient,',')>0)
	{
		list($pa_last,$pa_first) = explode(',',$patient);
	}else{
		list($pa_first, $pa_last) = explode(' ',$patient);
	}
	$pa_last = trim($pa_last);
	$pa_first = trim($pa_first);

	$patients = $wpdb->get_results("select * from ci_patient where pa_first='{$pa_first}' and pa_last='{$pa_last}' limit 2");
	if(count($patients)==1)
	{
		foreach($patients as $p)
		{
			$patient_id = $p->pa_ID;
			$patientobj = $p;
		}
	}
}else if(!$patientobj && $patient_id > 0)
{
	$patientobj = $wpdb->get_row("select * from ci_patients where pa_ID='{$patient_id}'");
}

$tabs = array();
foreach($access_btypes as $btype)
{
	$filepath = trpdd_get_file_path($btype,'patient_trends');
	if(file_exists($filepath) && $filepath != __FILE__)
	{
		$tabs[$btype] = tr_pdd_get_typename($btype).' Trends';
	}

}

$tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($tabs);
if(!isset($tabs[$tab])){
	$tab = key($tabs);
}
if(!isset($_REQUEST['trajax'])) {
	?>
    <h2 class="nav-tab-wrapper">
		<?php

		foreach ( $tabs as $k => $txt ) {
			$class = $tab == $k ? 'nav-tab-active' : '';
			$link  = Tr_Base_Class_V6::link( array( 'tab'         => $k,
			                                        'act'         => '',
			                                        'id'          => '',
			                                        'batch_id'    => '',
			                                        'patient_mrn' => '',
			                                        's'           => '',
			                                        'search'      => '',
			                                        'paged'       => '',
			                                        'from'        => '',
			                                        'to'          => '',
			                                        'patient_id'  => '',
			                                        'orderby'     => '',
			                                        'order'       => '',
			                                        'patient'     => ''
			), false );
			echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
		}
		?>
    </h2>
	<?php Tr_Session_Class::show()?>

    <div class="wrap">
        <h2>Patient Trends</h2>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>"/>
            <input type="hidden" name="tab" value="<?php echo isset($_GET['tab'])? $_GET['tab'] : '' ?>"/>
            <span class="group_hide_by_summary">
                <label>Patient</label>
                <input type="text" class="patient_auto" name="patient" value="<?php echo stripslashes($patient) ?>"/>
                <label>MRN</label>
                <input type="text" name="patient_mrn" value="<?php echo $patient_mrn ?>" placeholder="Patient MRN"/>
                <input type="hidden" name="patient_id" id="patient_id" value="<?php echo $patient_id ?>"/>
            </span>
            From: <input type="text" class="hasdate" name="from" value="<?php echo @$from_date ?>" style="width:100px"/>
            <label id="summarytodate">To:</label>
            <input type="text" class="hasdate today" name="to" value="<?php echo @$to_date ?>" style="width:100px"/>
			<?php
			if(tr_get_option('ci_pdf_data','show_ptrends_groups') && $tab=='blood'){
				$groups = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups 
                        where tg_show_req = 1 and tg_type='{$tab}' order by tg_name asc");
				?>
                <select name="panelid" style="width:180px">
                    <option value="">All Group</option>
					<?php
					foreach($groups as $group)
					{
						$selected = $group->tg_ID == $selected_panelid? 'selected':'';
						echo '<option value="'.$group->tg_ID.'" '.$selected.'>'.$group->tg_name.'</option>';
					}
					?>
                </select>
                <label><input type="checkbox" name="summary" value="1" <?php checked($summary)?>/> Summary </label>
				<?php
			}
			?>
            <input type="submit" class="button bsty6" value="Submit"/>
            <input type="submit" name="trajax" class="button exportbtn" value="Export"/>
        </form>
    </div>
	<?php
	if ( $patientobj ) {
		echo '<h4>';
		echo $patientobj->pa_last . ', ' . $patientobj->pa_first;
		if ( ! empty( $patientobj->pa_mrn ) ) {
			echo ' - MRN: ' . $patientobj->pa_mrn;
		}
		echo '</h4>';
	}
}

$filepath = trpdd_get_file_path($tab,'patient_trends');
if(file_exists($filepath) && $filepath != __FILE__)
{
	$btype = $tab;
	include_once($filepath);
}
?>

<div style="display:none" id="chart_result"></div>
<style>
    h4.center{text-align: center}
</style>
<script src="https://www.gstatic.com/charts/loader.js"></script>
<script>
    var $cur_year = '<?php echo date_i18n('Y')?>';
    jQuery(function($){
        google.charts.load("current", {packages:["corechart"]});

        $('.patient_auto').autocomplete({
            source: ajaxurl+'?action=ajax&autocomplete=patient_auto&btype=<?php echo $btype?>',
            minLength: 1,
            select: function( event, ui ) {
                if(ui.item)
                {
                    $('input#patient_id').val(ui.item.id).data('name',ui.item.value);
                }
            }
        }).change(function () {
            if($(this).val()!=$('input#patient_id').data('name')){
                $(this).val('');
            }
        }).on('keypress',function () {
            $('[name="patient_mrn"]').val('');
        });
        $('[name="patient_mrn"]').on('keypress',function(){
            $('input#patient_id').val('');
            $('[name="patient"]').val('');
        })
        $('a.show_chart_trends').click(function () {
            $('#chart_result').html('<h4 class="center">'+$(this).html()+'</h4><div id="chart_div"></div>');
            tb_show('Chart','#TB_inline?width=800&height=350&inlineId=chart_result');
            var $json = $(this).data('json');
            console.log(typeof $json);
            var data = google.visualization.arrayToDataTable($json);

            var options = {
                legend: 'none',
                colors:['#000','#000','#000','#000'],
                series:{
                    0: { lineDashStyle: [6, 6],pointShape:'none' ,pointSize:0,tooltip:false,lineWidth:4},
                    1: { lineDashStyle: [6, 6],pointShape:'circle' ,pointSize:8,tooltip:true,lineWidth:4},
                    2: {lineDashStyle: [0, 0],pointShape:'circle' ,pointSize:8,tooltip:true,lineWidth:4}
                }
            };

            for(var i in $json[0])
            {
                if(i<=3 || typeof $json[0][i] !='string')continue;
                if($json[0][i].indexOf('Cutoff')>-1){
                    options.series[i-1] = { pointShape:'none' ,pointSize:0,style:'line',lineWidth:1};
                    options.colors[i-1] = 'green';
                }
                if($json[0][i].indexOf('Limit')>-1){
                    options.series[i-1] = { pointShape:'none' ,pointSize:0,style:'line',lineWidth:1};
                    options.colors[i-1] = 'red';
                }
            }

            var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
            chart.draw(data, options);
            return false;

        })
        $('input[name="summary"]').change(function () {
            if($(this).is(':checked')){
                var $vl = $('[name="from"]').val();
                if($vl!='') {
                    var $arr = $vl.split('-');
                    $vl = $cur_year+'-'+$arr[1]+'-'+$arr[2];
                    $('[name="from"]').val($vl);
                }
                $('.group_hide_by_summary').hide();
            }else{
                $('.group_hide_by_summary').show();
            }
        })
    })
</script>