<div class="break page2 staticpage">
	<div class="pageheader">
		<div class="logo">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo2'] ?>" style="width:46px" />
			<span><?php echo $settings['pdf_title'] ?></span>
		</div>
		<div class="title">
			<div class="t1">Table Of Contents</div>
			<div class="t2">Your Guide to Navigating This Report</div>
		</div>
	</div>
	<div class="pagecontent">
		<table class="navigation">
			<tr>
				<td class="numb"><span class="circle">1</span></td>
				<td>
					<table>
						<tr>
							<td class="grouphead">Introduction</td>
							<td class="dots"></td>
							<td class="pagen">1</td>
						</tr>
					</table>
					<ul>
						<li>Welcome Message</li>
						<li>About the Test</li>
					</ul>
				</td>
			</tr>
			<tr>
				<td class="numb"><span class="circle">2</span></td>
				<td>
					<table>
						<tr>
							<td class="grouphead">Your Guide</td>
							<td class="dots"></td>
							<td class="pagen">3</td>
						</tr>
					</table>
					<ul>
						<li>Guide to Interpreting Your Report</li>
					</ul>
				</td>
			</tr>
			<tr>
				<td class="numb"><span class="circle">3</span></td>
				<td>
					<table>
						<tr>
							<td class="grouphead">Test Results</td>
							<td class="dots"></td>
							<td class="pagen">6</td>
						</tr>
					</table>
					<ol>
						<?php
						$tests = array(
							'BetaDx', 'C-peptide', 'Hemoglobin A1C', 'Insulin'
						);
						foreach ($tests as $test) {
							echo '<li>' . $test . '</li>';
						}
						?>
					</ol>
				</td>
			</tr>
			<tr>
				<td class="numb"><span class="circle">4</span></td>
				<td>
					<table>
						<tr>
							<td class="grouphead">Resources & Educational Insights</td>
							<td class="dots"></td>
							<td class="pagen">18</td>
						</tr>
					</table>
					<ul>
						<li>Personalized Health Suggestions</li>
						<li>Enhancing Your Knowledge</li>
					</ul>
				</td>
			</tr>
			<tr>
				<td class="numb"><span class="circle">5</span></td>
				<td>
					<table>
						<tr>
							<td class="grouphead">Your Health Journey</td>
							<td class="dots"></td>
							<td class="pagen">21</td>
						</tr>
					</table>
					<ul>
						<li>The Importance of Regular Testing</li>
						<li>How to Track Your Progress</li>
					</ul>
				</td>
			</tr>
		</table>
	</div>
	<?php 
	$nopagenum = true;
	include($files_dir.'footer.php')?>
</div>