<?php
/*
 * Methaphetamine for tox
 */

if(!$has_Methamphetamine_dl || (empty($methamphetamine['d']) && empty($methamphetamine['l'])))
{
	$has_Methamphetamine = false;
}

if(empty($methamphetamine['comments'])) {
	$methamphetamine['comments'] = 'The d/l isomer identification is a follow up to a positive methamphetamine confirmation result. The d/l identification is reported as the percentage of Isomer present. A > 20 % concentration of d-isomer likely indicates the use of illicit Methamphetamine, unless the patient has been prescribed a D-Methamphetamine containing drug (desoxyn).';
}
if ($has_Methamphetamine  && $report['matrix']!='oral'): ?>
	<div class="frow">
		<table style="width:100%;page-break-inside: avoid" class="tblborder border tdborder small_table">
			<thead>
			<tr class="header_tbl">
				<th colspan="3" class="header_black">Methamphetamine Isomer</th>
			</tr>
			</thead>
			<tbody>
			<tr>
				<td style="width:20%;vertical-align: middle">D-Methamphetamine</td>
				<td style="width:10%;vertical-align: middle" class="center"><?php echo $methamphetamine['d'] ?>&nbsp;%</td>
				<td rowspan="2">
					<?php echo $methamphetamine['comments'] ?>&nbsp;
				</td>
			</tr>
			<tr>
				<td style="vertical-align: middle">L-Methamphetamine</td>
				<td class="center" style="vertical-align: middle"><?php echo $methamphetamine['l'] ?> %</td>
			</tr>
			</tbody>
		</table>
	</div>
<?php endif; ?>