<?php
global $wpdb;


$id                     = isset($_REQUEST['id']) ? $_REQUEST['id'] : $id;
$bid                    = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : $bid;


$sum                    = true; //now live sum
$has_report             = false;
$has_style2             = false;
$upload_dir             = tr_wp_upload_dir();
$logo_dir               = $upload_dir['basedir'] . '/logos/';
$logo_url               = $upload_dir['baseurl'] . '/logos/';


$settings               = get_option('ci_result_settings_req', array());
$notes                  = get_option('ci_notes_result_req', array());

$style_num              = tr_get_option('ci_pdf_data', 'req_pdf_style', 4);
$req_pdf_show_conc    = tr_get_option('ci_pdf_data', 'req_pdf_show_conc');

$pdf_title 			    = tr_get_option('ci_pdf_data', 'req_pdf_title');
$showdatetime           = tr_get_option('ci_pdf_data', 'results_dtime');

$result_dir     		= tr_get_result_upload_dir();


if (empty($pdf_title)) {
	$pdf_title = 'Test Lab Report';
}


if (empty($report['mid']) && $mid > 0) {
	$report['mid'] = $mid;
}
$report = tr_pdd_get_report_by_id($id, 'req', $report['mid']);
$has_report = true;
$imagewelcome = 'data:image/png;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/bg1.jpg'));
$imagebottomleft = 'data:image/png;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/bottomleft.jpg'));
$imagetopright = 'data:image/png;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/topright.jpg'));
$imagemiddle = 'data:image/png;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/mid.jpg'));

?>
<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo $report['assen'] ?></title>
	<style>
		@page {
			margin: 0;
		}

		body {
			margin: 0;
			font-size: 18px;
			color: #003f3c;
			font-family: Helvetica;
		}

		p {
			margin-bottom: 15px;
		}

		table {
			border: 0;
			width: 100%;
		}

		br {
			height: 0;
			margin-bottom: 10px;
			line-height: 10px;
		}

		ul,
		ol {
			margin: 10px 0;
		}

		ul li,
		ol li {
			margin-bottom: 5px;
		}

		ul.limg10 li,
		ol.limg10 li {
			margin-bottom: 10px;
		}

		.break {
			page-break-before: always;
			border: 0;
			height: 0;
		}

		.fontDejavu {
			font-family: "DejaVu Sans";
		}

		.size0 {
			font-size: 18px;
			line-height: 20px;
		}

		.size1 {
			font-size: 20px;
			line-height: 24px;
		}

		.size2 {
			font-size: 24px;
			line-height: 28px;
		}

		.size3 {
			font-size: 30px;
		}

		.size3_5 {
			font-size: 35px;
		}

		.size4 {
			font-size: 40px;
		}

		.mtop10 {
			margin-top: 10px;
		}

		.mtop20 {
			margin-top: 20px;
		}

		.mtop30 {
			margin-top: 30px;
		}

		.center {
			text-align: center;
		}

		.right {
			text-align: right;
		}

		.bottom {
			vertical-align: bottom;
		}

		.contentbottom {
			position: absolute;
			bottom: 50px;
			left: 1%;
			width: 98%;
			line-height: 40px;
		}

		.contentbottom td {
			vertical-align: bottom;
		}

		.pagecontent {
			margin: 40px;
		}

		.staticpage {
			margin: 0;
			padding: 0;
			height: 1056px;
			width: 100%;
			position: absolute;
		}

		.pagewelcome {
			background: url(<?php echo $imagewelcome ?>) no-repeat;
		}

		.pagebgbottomleft {
			background: url(<?php echo $imagebottomleft ?>) no-repeat left bottom;
		}

		.pagebgtopright {
			background: url(<?php echo $imagetopright ?>) no-repeat top right;
		}

		.pagebgmiddle {
			background: url(<?php echo $imagemiddle ?>) no-repeat middle center;
		}


		.whitebox {
			width: 460px;
			margin: 30px auto 0 auto;
			background: #fff;
			padding: 30px;
			font-size: 24px;
			line-height: 30px;
			border-radius: 6px;
		}

		.roundbox {

			background: #caeecc;
			width: 300px;
			height: 300px;
			border-radius: 150px;
		}

		.roundbox2 {
			margin-top: 60px;
			margin-left: 60px;
			text-align: center;
			background: #ffd996;
			width: 180px;
			height: 180px;
			border-radius: 90px;
			position: absolute;
		}

		.boxtext {
			line-height: 90px;
			text-align: center;
			font-size: 30px;
		}

		.boxcolor {
			display: inline-block;
			width: 50px;
			height: 10px;
			margin-right: 20px;
			margin-top: 10px;
		}

		.boxcolor1 {
			background: #caeecc;
		}

		.boxcolor2 {
			background: #fff59c;
		}

		.boxcolor3 {
			background: #ffd996;
		}

		.boxcolor4 {
			background: #f24822;
		}
	</style>
</head>

<body>

	<div class="pagewelcome staticpage">
		<div class="pagecontent">
			<div style="font-size:100px;font-family:Arial"><?php echo $settings['pdf_title'] ?></div>
			<div class="center" style="margin-top:70px;font-size:40px;">Your Partner in Optimized Health</div>
			<div class="center" style="margin-top:10px;font-size:70px;">The Diabetes</div>
			<div class="center" style="margin-top:0px;font-size:70px;">Prevention Test</div>

			<div class="center">
				<img src="<?php echo TRCPDD_PATH . 'reports/images/look.jpg' ?>" style="border-radius: 8px;" />
			</div>
			<div class="whitebox center">
				<div>Report Generated For <?php echo $report['patient_name'] ?></div>
				<div>Sample Date: <?php echo date('M d, Y', strtotime($report['doc'])) ?></div>
				<div>Sample ID: <?php echo $report['assen'] ?></div>
			</div>

			<div style="margin-top:16px">
				<table>
					<tr>
						<td><img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:110px" /></td>
						<td style="vertical-align: bottom;text-align: right;"><?php echo $settings['pdf_contactus'] ?></td>
					</tr>
				</table>
			</div>
		</div>
	</div>



	<div class="break pagewelcome staticpage">
		<div class="pagecontent">
			<div class="center"><img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:110px" /></div>
			<div class="center" style="font-size:28px;line-height:50px;margin-top:70px">
				Kihealth si transforming how people detect, treat and prevent disease through a state of hte art approach connecting diagnostics, data, and at-home healthcare.<br>
				We are seekingot improve quality folife and lifespan through a state-of-the-art portfolio of preventive<br>
				diagnostics. Our mission is to empower our customers to learn about their bodies and make the most informed<br>
				decisions so they can avoid chronic diseases and<br>
				maintain welness as they age. We understand that knowledge about the body si hte Ki (Key)ot long-term success. Cheers ot taking the first step on your journey!
			</div>
			<div class="center" style="font-size:60px;font-family:Arial;margin-top:70px"><?php echo $settings['pdf_title'] ?></div>

			<div class="center" style="margin-top:70px">
				<a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a>
			</div>
		</div>
	</div>


	<div class="break pagebgbottomleft staticpage">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="mtop30" style="font-size:40px">Welcome</div>

			<div class="size2 mtop30">
				<div style="margin-bottom:30px">Jennifer</div>
				<div style="margin-bottom:10px">We are excited you decided to learn more about your metabolic health using our novel diagnostic test.</div>
				<div style="margin-bottom:10px">This groundbreaking early detection diabetes test si the first of it's kind available on the market and wil enable you to get a look
					at your metabolic health profile focusing on your beta cell loss and function.</div>
				<div style="margin-bottom:10px">Our mission is to empower our customers to learn about their bodies and make the most informed decisions so they can avoid chronic diseases and maintain wellness as they age. We operate under the philosophy that you can only improve what you
					measure, which is why we measure actionable biomarkers found in the blood. With the information in this report, you can change your lifestyle and take specific interventions to improve your metabolic function and improve your health.</div>
				<div style="margin-bottom:10px">By taking this Kihealth test, you have invested in yourself and enlisted us to provide the most accurate testing and up-to-date information to make your long-term health a reality.</div>
				<div style="margin-bottom:10px">Hopefully this is the first of many reports that we wil have the pleasure of sharing with you.</div>

				Sincerely,<br><br>
				The Kihealth Team
			</div>

			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>


	<div class="break pagebgtopright staticpage">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="size4 mtop30">Table of Contents</div>

			<div class="size3 mtop30">Your Metabolic Health Results</div>
			<ul class="size2" style="line-height:32px">
				<li>Summary of your metabolic health testing results</li>
			</ul>

			<div class="size3 mtop30">Details and Recommendations</div>

			<ul class="size2" style="line-height:32px">
				<li>Modifiable factors for health and longevity</li>
				<li>The Important Role Metabolic Health Plays</li>
				<li>Understanding the Important Role Beta Cells Play</li>
				<li>How Beta Cells Function</li>
				<li>How we measure your Beta Cell Function and Loss?</li>
				<li>Categories of Beta Cell Levels</li>
				<li>How do we define the optimum Beta Cell range?</li>
				<li>Potential Benefits of Optimal Metabolic Function</li>
				<li>Risk factors for suboptimal Metabolic Function</li>
				<li>Four ways to improve your Metabolic Function</li>
				<li>When should you take a Metabolic test again?</li>
				<li>Other Tests to Consider</li>
			</ul>

			<div class="size3 mtop30">Questions?</div>


			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right bottom"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>


	<div class="break pagebgtopright staticpage">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="size4 mtop30">Results</div>
			<div class="size3 mtop30">Your Metabolic Health (MH)</div>
			<div class="size3 ">result from the current test</div>

			<div class="roundbox" style="margin-top:20px">
				<div class="roundbox2">
					<div class="boxtext fontDejavu">4.8β</div>
				</div>
			</div>
			<div class="size0" style="margin-top:20px">
				4.8<span class="fontDejavu">β</span> is at the higher end of the deficient range, and 20.3% of the<br>
				higher end of the optimum range (100µM), Your Metabolic Health<br>
				Score falls into the Deficient range.
			</div>
			<div class="size2" style="margin:20px 0">Definitions of Categories</div>

			<div>
				<span class="boxcolor boxcolor1"></span> Optimal (7<span class="fontDejavu">β</span> - 10<span class="fontDejavu">β</span> )
			</div>
			<div><span class="boxcolor boxcolor2"></span> Suboptimal ( 5<span class="fontDejavu">β</span> - 6.9<span class="fontDejavu">β</span> )</div>
			<div><span class="boxcolor boxcolor3"></span> Deficient (3<span class="fontDejavu">β</span> - 4.9<span class="fontDejavu">β</span> )</div>
			<div><span class="boxcolor boxcolor4"></span> Severely deficient (0<span class="fontDejavu">β</span> - 2.9<span class="fontDejavu">β</span>)</div>

			<div class="size0 " style="margin-top:20px">
				4.8<span class="fontDejavu">β</span> is at the higher end of the deficient range, and 20.3% of the<br>
				higher end of the optimum range (100µM), Your Metabolic Health<br>
				Score falls into the Deficient range.
			</div>

			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>


	<div class="break pagebgbottomleft staticpage">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="size4 mtop30">Modifiable Factors for Health and Longevity</div>

			<div class="size1 mtop30">Modifiable factors are aspects of one's lifestyle or environment that can be altered to improve health and longevity. Addressing these factors expectancy. etre are some onci isabe a tors a improved metabolic health and thus improved health and longevity:</div>


			<ul class="size1" style="line-height:36px">
				<li>Diet</li>
				<li>Physical Activity</li>
				<li>Sleep Quality</li>
				<li>Stress Management</li>
				<li>Maintain Healthy Weight</li>
				<li>Regular Health Screenings</li>
				<li>Mental and Social Well-being</li>
				<li>Limiting Toxin Exposure</li>
			</ul>

			<div class="size1">These factors have a huge impact on your health, and they al are controllable.</div>

			<div class="size1 mtop20">But to improve your health, you need a way to measure it. That's where Beta Cell screening comes in. Understanding your beta cell health is
				pivotal in realizing your true metabolic health, and metabolic health is the gateway to healthspan and disease prevention. This diagnostic measures both the amount of beta cells you have as well as how well they are functioning</div>


			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right bottom"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>

	<div class="break pagebgbottomleft staticpage">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="size3_5 mtop30">The Important Role Metabolic Health Plays</div>

			<div class="size1 mtop20">Metabolic health is increasingly recognized as the foundational piece ni a wide range of chronic diseases, even beyond those traditionally classified as metabolic disorders like diabetes or obesity. Here's why metabolic health is considered so central to optimal health and being
				able to prevent disease:</div>


			<ol class="size0">
				<li><b>Energy Regulation:</b> At its core, metabolism refers ot the myriad chemical reactions that produce and use energy within our cells. This energy powers nearly every cellular function. Any disruption ni energy production, regulation, or utilization can lead to cellular dysfunction.</li>
				<li><b>Inflammation:</b> Metabolic imbalances can induce chronic low-level inflammation. This state, often termed "meta-inflammation," is now linked to a host of conditions from cardiovascular disease to cancer, neurodegenerative disorders, and more.</li>
				<li><b>Insulin Resistance:</b> Insulin is a critical hormone for regulating glucose uptake and energy storage. Insulin resistance, a hallmark of poor metabolic health, increases the risk of type 2 diabetes, cardiovascular diseases, and some cancers.</li>
				<li><b>Hormonal Disruptions:</b> Metabolic health influences the balance of many hormones beyond insulin, including leptin (related ot hunger and energy storage), ghrelin (hunger hormone), and hormones related to stress, like cortisol. Imbalances can contribute to a range of
					disorders, from obesity to depression.</li>
				<li><b>Lipid Dysregulation:</b> Poor metabolic health often comes with abnormal lipid profiles, including high levels of low-density lipoprotein (LDL) cholesterol or triglycerides, which are risk factors for atherosclerosis and subsequent heart diseases.</li>
				<li><b>Endothelial Dysfunction:</b> The endothelium, a thin layer of cells lining our blood vessels, plays a vital role ni vascular health. Metabolic disorders can impair its function, leading to a higher risk of hypertension, heart diseases, and stroke.</li>
				<li><b>Obesity:</b> One of the visible signs of metabolic dysfunction is obesity, particularly central or abdominal obesity. Adipose tissue, especially when ni excess, is not just a passive storage depot. It releases inflammatory cytokines and can profoundly impact overall health, increasing the risk of numerous diseases.</li>
				<li><b>Cellular Senescence:</b> Poor metabolic health can accelerate cellular aging or senescence. Senescent cells can contribute to tissue dysfunction and inflammation, playing roles in age-related diseases.</li>
			</ol>

			<div class="size1">This is why the Kihealth "Metabolic" test is the foundational diagnostic
				tool to understanding your current health, tracking effectiveness of intervention, and preventing disease.</div>


			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>


	<div class="break staticpage">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="size3_5 mtop30">How do we measure your Metabolic Health?</div>

			<div class="size1 mtop20">Understanding the Important Role Beta Cells Play</div>

			<div class="size1 mtop20">Beta cells are specialized cells located in the pancreas, specifically within clusters known as the islets of Langerhans. These cells play a crucial role in glucose metabolism and overall energy homeostasis in the body. If a person doesn't have any functioning beta cells, it leads to a complete lack of insulin production. The absence of insulin has profound metabolic consequences that leads to disease and life-threatening circumstances. If glucose doesn't have insulin available to be absorbed into your cells, the glucose remains in your blood and becomes toxic.</div>

			<div class="size0 mtop10">
				Beta cells function as the body's glucose sensors and the primary insulin producers, ensuring that blood glucose levels remain balanced, and cells receive the energy they need. Their proper function is crucial for metabolic health, and dysfunction or loss of beta cells is central to the pathology of diabetes and many other diseases. You can now see that as beta cells die and or don't function effectively the body's cellular energy processes are affected and without adequate energy a cascade of health concerns persists.
			</div>
			<div class="size3 mtop30">Correlation Chart</div>

			<div style="margin:10px">
				<img src="<?php echo TRCPDD_PATH . 'reports/images/chart1.jpg' ?>" style="width:600px">
			</div>

			<div class="size3">Categories of Metabolic Health Levels</div>

			<div style="margin-left:10px;margin-top:10px">
				<div class="boxcolor boxcolor4" style="width:100px;height:60px;margin:0 -5px 0"></div>
				<div class="boxcolor boxcolor3" style="width:100px;height:60px;margin:0 -5px 0"></div>
				<div class="boxcolor boxcolor2" style="width:100px;height:60px;margin:0 -5px 0"></div>
				<div class="boxcolor boxcolor1" style="width:100px;height:60px;margin:0 -5px 0"></div>
			</div>

			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>


	<div class="break staticpage">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="size3_5 mtop30">How do we define the optimum Metabolic Health Range?</div>

			<div class="size1 mtop20">Kihealth invented the BetaDx test and combine it with C-Peptide, utilizing these 2 biomarkers to assess metabolic health. BetaDx measures the amount of Beta Cell loss and C-peptide measures Beta Cell function. The two measurements combined give us a comprehensive picture of Beta Cell health which is the first signaler to declining or concerning metabolic health issues.<br><br>
				BetaDx: BetaDx assay is a high-throughput, accurate testing technology utilizing cutting edge droplet digital PCR based analysis that amplifies the amount of beta cell death occurring in the pancreas.
				We calculate the amount of Beta Cell loss against a healthy control of someone the same age and produce a risk score. The Kihealth BetaDx assay is highly reproducible with the average coefficient of variation of 3.1% and the coefficient of correlation (R2) of 0.98.<br><br>
				C-Peptide: C-peptide and insulin are co-secreted by the beta cells of the pancreas in equimolar amounts. As a result, measuring the levels of C-peptide in the bloodstream provides an indirect way to evaluate endogenous insulin secretion and, by extension, beta cell function. We compare the amount of Beta Cell death with C-Peptide to confirm an accurate metabolic risk score.</div>

			<div class="size3 mtop10">
				Distribution of icNAD Levels in Different Age Groups
			</div>

			<div style="margin:10px">
				<img src="<?php echo TRCPDD_PATH . 'reports/images/chart2.jpg' ?>" style="width:480px">
			</div>


			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>


	<div class="break staticpage pagebgmiddle">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="size4 mtop30">Potential Benefits of Optimal<br>Metabolic Health</div>

			<div class="size1 mtop20">Optimal metabolic health is afoundation foroverall well-being and longevity. tI refers to the ideal functioning of various metabolic processes ni the body, such as energy production, glucose regulation, lipid metabolism, and hormonal balance. Here are the potential benefits of having optimal metabolic health:</div>

			<ol class="limg10">
				<li>Reduced Risk of Chronic Diseases</li>
				<li>Enhanced Energy Levels</li>
				<li>Optimized Body Composition</li>
				<li>Improved Mood and Cognitive Function</li>
				<li>Healthier Skin and Aging</li>
				<li>Strong Immune Function</li>
				<li>Reduced Inflammation</li>
				<li>BetterHormonalBalance</li>
				<li>Bone Health</li>
				<li>Reduced Risk of Certain Cancers</li>
				<li>Optimal Digestive Health</li>
				<li>Healthy Sleep Pattern</li>
				<li>Metabolic health is linked to the body's circadian rhythm, potentially Leading to improved sleep quantity and patterns</li>
			</ol>

			<div class="size0">In essence, optimal metabolic health touches virtually every aspect of our well-being. It's a cornerstone for longevity and a high quatity of life</div>

			<div class="size3 mtop30">Questions?</div>

			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>

	<div class="break staticpage">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="size3_5 mtop30">Four ways to Improve Your<br> Metabolic Health Score</div>

			<div class="size1 mtop20">
				Improving metabolic health is pivotal in reducing the risk of various chronic diseases, including cardiovascular disease, type 2 diabetes, and even certain cancers. Here are four of the most effective and evidence-based strategies to enhance metabolic health:
				<br>
				There are four major approaches.
			</div>

			<div class="size2" style="margin:10px 0">Four Approaches</div>

			<div class="size1">1. Regular Physical Activity:</div>

			<ul class="limg10">
				<li>Aerobic Exercise: Activities like walking, running, cycling, or swimming increase cardiovascular fitness and improve insulin sensitivity. Aim for at least 150 minutes of moderate-intensity or 75 minutes of vigorous-intensity aerobic exercise per week.</li>
				<li>Strength Training: Incorporate resistance exercises into your routine 2-3 times a week to build muscle mass. Muscle is metabolically active and helps improve insulin sensitivity and glucose metabolism. Strength training reduces the burden on beta cells and allows muscle tissue to dispose of glucose without insulin.</li>
			</ul>

			<div class="size1">2. Dietary Modifications:</div>
			<ul class="limg10">
				<li>Whole Foods: Focus on a diet rich in whole foods, including vegetables, fruits, lean proteins, and healthy fats.</li>
				<li>Limit Refined Sugars and Processed Carbohydrates: These can spike blood glucose and contribute to insulin resistance.</li>
				<li>Healthy Fats: Incorporate sources of omega-3 fatty acids, such as fish, flaxseed, and walnuts. Limit trans fats and reduce seed oils and artificial fats.</li>
				<li>Stay Hydrated: Drink plenty of water throughout the day rich in minerals. Limit sugary drinks and excessive caffeine.</li>
				<li>Caloric Control: Reduce overall caloric intake or utilize time restricted feeding protocols to promote reduction in adipose tissue and senescent cells.</li>
			</ul>

			<div class="size0">In essence, optimal metabolic health touches virtually every aspect of our well-being. It's a cornerstone for longevity and a high quatity of life</div>


			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>


	<div class="break staticpage">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="size3_5 mtop30">Four ways to Improve Your<br>Metabolic Health Score Cont.</div>

			<div class="size1 mtop20">
				Improving metabolic health is pivotal in reducing the risk of various chronic diseases, including cardiovascular disease, type 2 diabetes, and even certain cancers. Here are four of the most effective and evidence-based strategies to enhance metabolic health:
				<br>
				There are four major approaches.
			</div>

			<div class="size1 mtop20">3. Maintain a Healthy Weight:</div>

			<ul class="limg10">
				<li>Weight Management: If overweight, losing even 5-10% of body weight can make a significant difference in metabolic parameters.</li>
				<li>Waist Circumference: Central obesity (excessive fat around the stomach and abdomen) is particularly linked to metabolic syndrome. Monitoring and managing waist circumference can be more impactful than just considering body weight.</li>
				<li>Body Composition: Focus on reducing body fat percentage while maintaining or increasing lean muscle mass.</li>
			</ul>

			<div class="size1">4. Manage Stress and Prioritize Sleep:</div>
			<ul class="limg10">
				<li>Stress Management: Chronic stress can elevate cortisol levels, which can interfere with glucose metabolism. Techniques like meditation, deep breathing exercises, and time management can be beneficial.</li>
				<li>Quality Sleep: Aim for 7-9 hours of quality sleep per night. Sleep has a profound impact on metabolic health, influencing hormones that regulate appetite, stress, and glucose metabolism.</li>
			</ul>

			<div class="size0">While these strategies are foundational for most people, individual needs and circumstances can vary. Therefore, it's always a good idea to consult with a healthcare or nutrition professional when making significant changes to one's lifestyle or health regimen.</div>


			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>


	<div class="break staticpage pagebgtopright">
		<div class="pagecontent">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:40px" />

			<div class="size3_5 mtop30">When Should You Take the Test Again?</div>

			<div class="size1 mtop20">
				Your Beta Cell test result, like any other test, reflects your Beta cell status at the time your blood was drawn. Your beta Cell health status will change with age, external and internal changes, and your health optimization and intervention strategies. It is therefore critical to take the Beta Cell test again at regular intervals.<br><br>
				If you are in the optimum range and there is no major change in lifestyle, nutrition, health status, and intervention strategies, you may consider taking the test again in 6-12 months.<br><br>
				If your Beta Cell test results are not within a healthy range, consider taking the test again in 3-6 months after you've implemented steps to improve your metabolic health.<br><br>
				Keep in mind that you may need to try multiple strategies until you find one that works for you. Repeated testing is the only way you can measure progress towards your personal goal and effectiveness or interventions.
			</div>

			<div class="size2 mtop20">Questions?</div>

			<div class="size0 mtop10">If you have any questions or concerns, don't hesitate to contact us at:</div>
			<div class="size2 center " style="background:#d1f6d3;padding:20px;margin:20px 0">
				help@kihealth.com<br>
				+1 (888)-kih-ealt<br>
				559 W Twincourt Trail, Saint Augustine, LF 32095
			</div>
			<div class="size0">We want to thank you for letting us be a part of your journey for optimized health.</div>
			<div class="size0 mtop20"><b>Disclaimer</b><br>
				The BetaFirst Test is not a diagnostic test and is not approved by the FDA. TheBetaFirst Test does not evaluate many other factors that may be important for your health status and disease diagnosis and should NOT be used as replacement for your medical care by your physicians.</div>


			<table class="contentbottom">
				<tr>
					<td style="font-size:50px;font-family:Arial;"><?php echo $settings['pdf_title'] ?></td>
					<td class="right"><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></td>
				</tr>
			</table>
		</div>
	</div>

	<div class="break pagewelcome staticpage">
		<div class="pagecontent">
			<div style="font-size:100px;font-family:Arial"><?php echo $settings['pdf_title'] ?></div>
			<div class="center" style="margin-top:70px;font-size:90px;">Understanding<br>how your body<br>works is the key<br>to longevity</div>

			<table class="contentbottom" style="bottom:120px;">
				<tr>
					<td><img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:110px" /></td>
					<td style="vertical-align: bottom;text-align: right;"><?php echo $settings['pdf_contactus'] ?></td>
				</tr>
			</table>

		</div>
	</div>


</body>

</html>