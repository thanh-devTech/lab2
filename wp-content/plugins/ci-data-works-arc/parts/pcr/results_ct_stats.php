<?php
global $wpdb, $current_role, $ci_all_btypes;

include_once(TRCPDD_PATH.'pages/formdata.php');

$from_date  = isset($_REQUEST['from'])? $_REQUEST['from']:'';
$to_date    = isset($_REQUEST['to'])? $_REQUEST['to']:'';

if(empty($to_date))
{
	$to_date = date_i18n('Y-m-d');
}

if(empty($from_date))
{
	$from_date = date('Y-m-d', strtotime('-3 months', strtotime($to_date)));
}
$from_date  = date('Y-m-d', strtotime($from_date));
$to_date    = date('Y-m-d', strtotime($to_date));


$child_tabs = array(
	'n1n2' => 'N1,N2 & RNAsep',
	'n1n2rp' => 'N1,N2 & RP',
	'n1rp' => 'N1 & RP',
	'ms2' => 'ORF1ab,N Gene & MS2',
	'famvic' => 'FAM & VIC',
	'ic' => 'COVID-19 & IC',
);


if(!tr_get_option('ci_pdf_data','pcrcovid_famvic')){
	unset($child_tabs['famvic']);
}else{
	unset($child_tabs['ic']);
}

if(!tr_get_option('ci_pdf_data','pcrcovid_n12rp')){
	unset($child_tabs['n1n2']);
}

if(!tr_get_option('ci_pdf_data','pcrcovid_n12_r_p')){
	unset($child_tabs['n1n2rp']);
}

if(!tr_get_option('ci_pdf_data','pcrcovid_ms2rp') && !tr_get_option('ci_pdf_data','pcrcovid_ms2rpsgen')){
	unset($child_tabs['ms2']);
}

if(!tr_get_option('ci_pdf_data','pcrcovid_n1_rp')){
	unset($child_tabs['n1rp']);
}

$ctab = !empty($_REQUEST['ctab']) && isset($child_tabs[$_REQUEST['ctab']])? $_REQUEST['ctab']: key($child_tabs);
$instrument = 'IC';
if($ctab=='n1n2'){
	$instrument = 'N1N2';
}else if($ctab=='n1n2rp'){
	$instrument = 'N1N2RP';
}else if($ctab=='ms2'){
	$instrument = 'MS2';
}else if($ctab=='n1rp'){
	$instrument = 'N1RP';
}else if($ctab=='famvic'){
	$instrument = 'FAMVIC';
	
}

$tests_all = trpdd_get_covid_tests($instrument);
$tests_pos = $tests_all[0];
$colors = array(
	$tests_all[0] => '#FD9F45',
	$tests_all[1] => '#B20710',
);
if(!is_array($tests_pos))
{
	$tests_pos = array();
}
if(!is_array($tests_all))
{
	$tests_all = array();
}
$test_pos_str = implode("','", $tests_pos);
$test_all_str = implode("','", $tests_all);

$batch_min = str_replace('-','',$from_date);
$batch_max = str_replace('-','',$to_date);

$sql = "select 
					sum( IF(rs.conc='NaN' or rs.conc='No Ct', 45, rs.conc) ) as sumb, 
					count(*) as countb,
					rs.drug, 
					ba.batch_id 
					from ci_results_pcr rs
					join ci_reports_pcr re On re.id = rs.report_id
					join ci_batches_pcr ba On ba.assen = re.patient_id
					where 
					(rs.drug in ('{$test_all_str}') or (rs.`pos-neg` = 'POS' and rs.drug in ('{$test_pos_str}'))) 
					and ba.batch_id >= {$batch_min} 
					 and ba.batch_id <= {$batch_max}
					 and (rs.conc > 0 ) 
					group by ba.batch_id, rs.drug
					order by ba.batch_id asc";
$rs = $wpdb->get_results($sql);

$labels     = array();
$datasets   = array();

$i = 0;
foreach($tests_all as $t){
	$datasets[$t] = array(
		'label' => $t,
		'data' => array(),
		'backgroundColor' => $colors[$t],
		'borderColor' => $colors[$t],
		'fill' => false
	);
}
foreach($rs as $row)
{
	$row->drug = strtoupper($row->drug);
	if(!in_array($row->batch_id, $labels)){
		$labels[$i] = $row->batch_id;
		foreach($tests_all as $t){
			$datasets[$t]['data'][$i]=0;
		}
		$i++;
	}

	$num = array_search($row->batch_id,$labels);
	//echo $row->batch_id,'=>',$num,'<br>';
	

	$datasets[$row->drug]['data'][$num] = number_format($row->sumb / $row->countb, 2);
}


$datasets_arr = array();
foreach($datasets as $dataset)
{
	$datasets_arr[] = $dataset;
}


$data = array(
	'labels' => $labels,
	'datasets' => $datasets_arr,
);
$lines = array();
foreach($tests_all as $t){
	$average = count($datasets[$t]['data'])>0 ? array_sum($datasets[$t]['data'])/count($datasets[$t]['data']):0;
	$average = number_format($average,2);
	$lines[] = array(
		'type' => "line",
		'mode'=> "horizontal",
		'scaleID'=> "y-axis-0",
		'borderColor'=> $colors[$t],
		'value'=> $average,
		'label'=> array(
			'content'=> $average,
			'enabled'=> true,
			'position'=> "left",
            'backgroundColor'=>'transparent',
            'fontColor' => '#000',
            'fontStyle' => 'normal',
            'fontSize' => 9,
            'xAdjust' => 0
		)
	);
}

if(isset($_REQUEST['debug'])){
	var_dump($sql);
	echo '<br><br>';
	var_dump($data);
	echo '<br><br>';
	var_dump($datasets);
	echo '<br><br>';
	var_dump($datasets_arr);
}



?>

<div class="wrap">
	<?php
	if(count($child_tabs)>1)
	{
		?>
        <div class="wrap" style="margin-bottom:10px">
            <h2 class="nav-tab-wrapper">
				<?php
				foreach ($child_tabs as $k => $txt) {
					$class = $ctab == $k ? 'nav-tab-active' : '';
					$link = Tr_Base_Class_V6::link(array('ctab' => $k, 'act' => '', 'id' => '', 'orderby' => '', 'order' => '', 's' => '', 'paged' => ''), false);
					echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
				}
				?>
            </h2>
        </div>
		<?php
	}
	?>
    <form method="get" style="margin: 15px auto">
        <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
        <input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>

        <span id="summaryfromdate">
            From Date: <input type="text" class="hasdate" name="from" value="<?php echo @$from_date?>"/>
        </span>
        <label id="summarytodate">To Date:</label>
        <input type="text" class="hasdate" name="to" value="<?php echo @$to_date?>"/>

        <input type="submit" class="button bsty6" value="Run Report"/>
    </form>


    <canvas id="curve_chart" width="1200" height="600" style="background:#fff"></canvas>
</div>
<script type="text/javascript" src="<?php echo TRCPDD_URL?>js/Chart2.min.js?v=<?php echo TRCPDD_VS?>"></script>
<script src="<?php echo TRCPDD_URL?>js/chartjs-plugin-annotation.js"></script>


<script>
    var $data = <?php echo json_encode($data)?>;
    var $lines = <?php echo json_encode($lines)?>;
    var ctx = document.getElementById('curve_chart').getContext("2d");

    new Chart(ctx,{
        type:'line',
        data:$data,
        options: {
            responsive: true,
            title: {
                display: true,
                text: 'CT Stats'
            },
            tooltips: {
                mode: 'index',
                intersect: false,
            },
            hover: {
                mode: 'nearest',
                intersect: true
            },
            scales: {
                xAxes: [{
                    display: true,
                    scaleLabel: {
                        display: true,
                        labelString: 'Batch'
                    }
                }],
                yAxes: [{
                    display: true,
                    scaleLabel: {
                        display: true,
                        labelString: 'Value'
                    }
                }]
            },
            annotation: {
                annotations: $lines
            }

        }
    });
</script>