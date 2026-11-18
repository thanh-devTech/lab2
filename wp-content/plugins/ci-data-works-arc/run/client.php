<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

global $astm_num;
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

const SOH = 0x01;
const STX = 0x02;
const ETX = 0x03;
const ETB = 0x17;
const EOT = 0x04;
const ENQ = 0x05;
const ACK = 0x06;
const NAK = 0x15;
const LF = 0x0A;
const VT = 0x0B;
const CR = 0x0D;
const FS = 0x1C;

function replacedata($output)
{
	$output = str_replace("\r", "", $output);
	$output = str_replace("\n", "", $output);
	$output = str_replace('<SOH>',chr(SOH),$output);
	$output = str_replace('<STX>',chr(STX),$output);
	$output = str_replace('<ETX>',chr(ETX),$output);
	$output = str_replace('<ETB>',chr(ETB),$output);
	$output = str_replace('<EOT>',chr(EOT),$output);
	$output = str_replace('<ENQ>',chr(ENQ),$output);
	$output = str_replace('<ACK>',chr(ACK),$output);
	$output = str_replace('<NAK>',chr(NAK),$output);
	$output = str_replace('<VT>',chr(VT),$output);
	$output = str_replace('<LF>',chr(LF),$output);
	$output = str_replace('<CR>',chr(CR),$output);
	$output = str_replace('<FS>',chr(FS),$output);
	return $output;
}

function replacecode($log)
{
	$log = str_replace(chr(SOH),'<SOH>',$log);
	$log = str_replace(chr(STX),'<STX>',$log);
	$log = str_replace(chr(ETX),'<ETX>',$log);
	$log = str_replace(chr(ETB),'<ETB>',$log);
	$log = str_replace(chr(EOT),'<EOT>',$log);
	$log = str_replace(chr(ENQ),'<ENQ>',$log);
	$log = str_replace(chr(ACK),'<ACK>',$log);
	$log = str_replace(chr(NAK),'<NAK>',$log);
	$log = str_replace(chr(LF),'<LF>'."\n",$log);
	$log = str_replace(chr(VT),'<VT>',$log);
	$log = str_replace(chr(CR),'<CR>'."\n",$log);
	$log = str_replace(chr(FS),'<FS>',$log);
	$log = str_replace("<CR>\n<LF>",'<CR><LF>',$log);
	return $log;
}

function cal_checksum($string)
{
	$crc = 0;
	for ($i=0;$i<strlen($string);$i++){
		$crc += ord(substr($string,$i,1));
	}
	//$crc = $crc%256;

	return strtoupper(substr(dechex($crc),-2)) ;
}
function buildpk_astm($msg,$is_end=true)
{
	global $astm_num;
	if($astm_num==0)
	{
		$astm_num = 1;
	}else{
		$astm_num++;
	}
	$msg = replacedata($msg);

	$endchar = $is_end? chr(ETX) : chr(ETB);
	$checksum = cal_checksum($astm_num.$msg.$endchar);
	$out = chr(STX).$astm_num.$msg;
	$out.= $endchar;
	$out.= $checksum;
	$out.= chr(CR).chr(LF);


	return $out;
}

if(isset($_GET['dim']) || $argv[1]=='dim'){


	function cal_checksum($string)
	{
		$crc = 0;
		for ($i=0;$i<strlen($string);$i++){
			$crc += ord(substr($string,$i,1));
		}
		return strtoupper(substr(dechex($crc),-2)) ;
	}

	$output = 'R<FS>*<FS><FS>ION12345<FS>1<FS><FS>0<FS>365609160921<FS>1<FS>1<FS>6<FS>NA<FS>116<FS>mmol/L<FS><FS>K<FS>2.6<FS>mmol/L<FS><FS>ALPI<FS>77<FS>mmol/L<FS><FS>ALB<FS>116<FS>mmol/L<FS><FS>K<FS>2.6<FS>mmol/L<FS><FS>BUN<FS>77<FS>mmol/L<FS><FS>';
	$output = str_replace('<STX>',chr(STX),$output);
	$output = str_replace('<ETX>',chr(ETX),$output);
	$output = str_replace('<ETB>',chr(ETB),$output);
	$output = str_replace('<EOT>',chr(EOT),$output);
	$output = str_replace('<ENQ>',chr(ENQ),$output);
	$output = str_replace('<ACK>',chr(ACK),$output);
	$output = str_replace('<FS>',chr(FS),$output);

	$output = chr(STX).$output.cal_checksum($output).chr(ETX);


	$service_port = isset($_GET['port']) ? $_GET['port'] : 8688;

	$address = isset($_GET['ip']) ? $_GET['ip'] : '127.0.0.1';//gethostbyname($host);
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket === false) {
		echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
	} else {
		echo "OK.\n";
	}

	echo "Attempting to connect to '$address' on port '$service_port'...";
	$result = socket_connect($socket, $address, $service_port);
	if ($result === false) {
		echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
	} else {
		echo "OK.\n";
	}

	socket_write($socket, $output);
	echo 'sent';


	echo "\nClosing socket...";
	socket_close($socket);


}
if(isset($_GET['astm']) || $argv[1]=='astm'){

	$output = chr(0x02)."R 01U0226         HLX68248751".chr(0x03);
	//$output = chr(0x02)."D 01U0002         HLX12427636    E54       58  55       38  56        8  57-      16  58-       1  59-     1.1  60     14.8  61       18  ".chr(0x03);
	//$output = chr(0x02)."D 01U0004         HLX12473939    E056       10  057       15  058       10  059-       8  060        1  061-     4.1  062-     1.0  063      113  064    14.54P 088        2  089      0.7  090        6  094       12  103       17  104-       9  105      7.8  106    1.004  107     26.1  109       60  ".chr(0x03);
	//$output = chr(0x02)."D 01U0002         HLX34587011    E056-     163  057-     445  058-     446  059-     129  060-     316  062-    98.7  063-    2194  064-   20.18  088-     129  089-    41.3  090-      84  094-    1992  104-     334  105      2.9  106    1.053p 107      6.5  111      2.3L 112   0.9884L 113      9.9L 114     14.7  115      8.4pL116-     0.1L ".chr(0x03);

	$service_port = isset($_GET['port']) ? $_GET['port'] : 8686;

	/* Get the IP address for the target host. */
	$address = isset($_GET['ip']) ? $_GET['ip'] : '127.0.0.1';//gethostbyname($host);

	/* Create a TCP/IP socket. */
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket === false) {
		echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
	} else {
		echo "OK.\n";
	}

	echo "Attempting to connect to '$address' on port '$service_port'...";
	$result = socket_connect($socket, $address, $service_port);
	if ($result === false) {
		echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
	} else {
		echo "OK.\n";
	}

	socket_write($socket, $output);
	echo 'sent';
	echo "Reading response:\n\n";
	while (($buf = socket_read($socket, 1))!==false) {
		if($buf == chr(0x03))
		{
			break;
		}
		$buf = str_replace(' ','_',$buf);
		echo $buf;

	}

	echo "\nClosing socket...";
	socket_close($socket);

}
else if(isset($_GET['hl7']) || $argv[1]=='hl7'){



	$data = "MSH|^~\&|CLC6410-1||igniteLIS||20190930185416||ORU^R01|1|P|2.3.1||||0||UNICODE|||
PID|1||||HLX12454244|^||O||||||||||||||||||||||^
OBR|1|HLX12454244|94|CLC6410-1^|N||20190930182515||1|N010^4||N||20190930182515|1|||||1||20190930080627||||||||||
OBX|NM|1|AMPH^1|330|ng/mL|0-500|N||F||||20190930185323|1|1|
OBX|NM|2|BARB^1|1|ng/mL|0-200|N||F||||20190930185346|1|1|
OBX|NM|3|BENZ^1|131|ng/mL|0-200|N||F||||20190930185351|1|1|
OBX|NM|4|COCM^1|-19|ng/mL|0-150|L||F||||20190930185355|1|1|
OBX|NM|5|OPI^1|736|ng/mL|0-300|H||F||||20190930185400|1|1|
OBX|NM|6|OXY^1|13|ng/mL|0-300|N||F||||20190930185405|1|1|
OBX|NM|7|THC^1|-10|ng/mL|0-50|L||F||||20190930185409|1|1|
OBX|NM|8|UCREA^1|199.2|mg/dL|20-250|N||F||||20190930185333|1|1|
OBX|NM|9|OXID^1|1|ug/mL|0-200|N||F||||20190930185047|1|1|
OBX|NM|10|pH^1|5.7|pH|4.7-7.8|N||F||||20190930185052|1|1|
OBX|NM|11|Spec Grav^1|1.028|g/mL|1.005-1.03|N||F||||20190930185056|1|1|
";



	if(isset($_GET['t1'])){
		$data = "MSH|^~\&|||||20200610124855-0400||NMD^N02^NMD_N02|5aa4beac-876c-4a4e-86bb-656627fd08cb|P|2.5.1|||NE|AL||UNICODE UTF-8\rNST|N\r";
	}
	if(isset($_GET['tm'])){
		$data = "MSH|^~\&|||||20200608145921-0400||OUL^R22^OUL_R22|6268aa59-0d39-45fc-8739-06a7d976d713|P|2.5.1|||NE|AL||UNICODE UTF-8|||LAB-29^IHE\rSPM|1|||\"\"|||||||P^Patient^HL70369\rSAC|||HLX12347109|||||||F1694|1||||11^Position^99ABT~1^Module^99ABT\rOBR||\"\"||162^COV-2 IgG^99ABT\rORC|SC||||CM\rTQ1|||||||||R^Routine^HL70485\rOBX|1|ST|162^COV-2 IgG^99ABT|1|0.02|{index_val}^Index^UCUM||\"\"|||F|||||Admin~Admin||Alinity ci-series^Abbott~AI03890^Abbott|20200608145149||||||||||RSLT\rTCD|162^COV-2 IgG^99ABT||||||||||UNDILUTED^^99ABT\rINV|162^^99ABT|NA^Not applicable^HL70383|CA^Calibration^99ABT|||||||||20200612133442|20200605133442|||16726FP00\rINV|162-1^^99ABT|NA^Not applicable^HL70383|SR^Single Test Reagent^HL70384|01219^^99ABT||||||||20200725||||16726FP00\rOBX|2|ST|162.I^COV-2 IgG^99ABT|1|Negative|||\"\"|||F|||||Admin~Admin||Alinity ci-series^Abbott~AI03890^Abbott|20200608145149||||||||||RSLT\rOBX|3|ST|162.R^COV-2 IgG^99ABT^S_RAW^Raw Supplemental^IHELAW|1|831|^RLU||\"\"|||F|||||Admin~Admin||Alinity ci-series^Abbott~AI03890^Abbott|20200608145149||||||||||RSLT\rOBX|4|EI|162.G^COV-2 IgG^99ABT^S_OTHER^Other Supplemental^IHELAW|1|7f050efa-6ce7-4d59-a944-7d0dee68ac2d|||\"\"|||F|||||Admin~Admin||Alinity ci-series^Abbott~AI03890^Abbott|20200608145149||||||||||RSLT\r";
	}

	if(isset($_GET['ar'])){
		$data = "MSH|^~\&|ARCHITECT|82852|||20210118130429||QBP^ZOS^QBP_Q11|fa17901b-08b7-4c5d-9adf-0e2c068e9a97|P|2.5.1||||||UNICODE UTF-8
SFT|ADD|9.40|ARCHITECT|\"\"
QPD|ZOS^Order for Specimen^ARCHITECT|239504768|HLX12586038
RCP|I||R
";
	}

	if(isset($_GET['ar2'])){
		$data = "MSH|^~\&|ARCHITECT|82852|||20210118130429||QBP^ZOS^QBP_Q11|fa17901b-08b7-4c5d-9adf-0e2c068e9a97|P|2.5.1||||||UNICODE UTF-8
SFT|ADD|9.40|ARCHITECT|\"\"
QPD|ZOS^Order for Specimen^ARCHITECT|239504768|HLX123586038
RCP|I||R
";
	}

	if(isset($_GET['qbp'])){
		$data = "MSH|^~\&|||||20160801135944||QBP^Q11^QBP_Q11|1b0f9f1d-03ce-4dcb-8af0-a68c5a873a34|P|2.5.1|||NE|AL|| UNICODE UTF-8|||LAB-27^IHE
QPD|WOS^Work Order Step^IHELAW|87|hxL67894325
RCP|I||R^Real Time^HL70394";
	}

	if (isset($_GET['qry'])) {
		$data = "MSH|^~\&|CLC6410-1||igniteLIS||20191007154503||QRY^Q02|2|P|2.3.1||||0||UNICODE|||
QRD|20191007154503|BC|D|2|||RD|HLX34577174||N008^2|N|T
QRF||20191007000000|20191007154503|||RCT|COR|ALL||
";
	} else if (isset($_GET['ack'])) {
		$data = "MSH|^~\&|CLC6410-1||igniteLIS||20191007154503||ACK^Q03|2|P|2.3.1||||0||UNICODE|||
MSA|AA|2||||0|
";
	}

	$data = str_replace("\r\n", "\r", $data);
	$data = str_replace("\n", "\r", $data);
	$output = chr(11) . $data . chr(28) . chr(13);


	if (isset($_GET['withnull'])) {
		$newdata = '';
		for ($i = 0; $i < strlen($output); $i++) {
			$newdata .= $output[$i] . chr(0);
		}
		$output = $newdata;
	} else {

	}
//$output.= chr(13);
	file_put_contents(dirname(__FILE__) . '/client.txt', $output);


	/* Get the port for the WWW service. */
	$service_port = isset($_GET['port']) ? $_GET['port'] : 8688;


	/* Get the IP address for the target host. */
	$address = isset($_GET['ip']) ? $_GET['ip'] : '192.64.78.66';//gethostbyname($host);
//$address = '94.177.180.71';//gethostbyname($host);

	/* Create a TCP/IP socket. */
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket === false) {
		echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
	} else {
		echo "OK.\n";
	}

	echo "Attempting to connect to '$address' on port '$service_port'...";
	$result = socket_connect($socket, $address, $service_port);
	if ($result === false) {
		echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
	} else {
		echo "OK.\n";
	}

	socket_write($socket, $output);


	echo "Reading response:\n\n";
	while ($buf = socket_read($socket, 2048)) {
		$buf = str_replace(chr(11), '<SB>', $buf);
		$buf = str_replace(chr(28), '<EB>', $buf);
		$buf = str_replace("\r", "\r", $buf);

		$arr = explode('\r', $buf);
		var_dump($buf);
		file_put_contents(dirname(__FILE__) . '/client.txt', $buf);

		var_dump($arr);
		break;
	}


	echo "Closing socket...";
	socket_close($socket);
	echo "OK.\n\n";
	var_dump(time());
}
else if(isset($_GET['xpt']) || $argv[1]=='xpt'){

	if(isset($_GET['test']) || $argv[2]=='test'){
		//$astm_num = 1;
		$str= buildpk_astm('H|\^&|||UIW_LIS|||||igniteLIS||P||20220307113830<CR>
Q|1|^ION12389323|^ION12389323|ALL||||||||O<CR>
L|1|N<CR>');
		echo replacecode($str);
		exit;
	}


	/* Get the port for the WWW service. */
	$service_port = isset($_GET['port']) ? $_GET['port'] : 8687;

	/* Get the IP address for the target host. */
	$address = isset($_GET['ip']) ? $_GET['ip'] : '127.0.0.1';

	/* Create a TCP/IP socket. */
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket === false) {
		echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
	} else {
		echo "OK.\n";
	}

	echo "Attempting to connect to '$address' on port '$service_port'...";
	$result = socket_connect($socket, $address, $service_port);
	if ($result === false) {
		echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
	} else {
		echo "OK.\n";
	}

	socket_write($socket, replacedata('<ENQ>'));


	if(isset($argv[2]) && $argv[2]=='rs'){

		$output = replacedata('<STX>1H|\^&|||UIW_LIS|||||igniteLIS||P||20220218090421<CR>
P|1|||||||U<CR>
O|1|ION12388904||^^^aHBs2|R||||||||||||||||||||F<CR>
R|1|^^^aHBs2^^^1^aHBsAg|1|||||F\R||||20220126105354|IRL28542117<CR>
C|1|I|Final Result Ru|I<CR>
C|2|I|Above Check|I<CR>
R|2|^^^aHBs2^^^1^COFF|1.00|<ETB>8A<CR><LF>');
		socket_write($socket, $output);

		$output = replacedata('<STX>2mIU/mL||||F\R||||20220126105354|IRL28542117<CR>
C|1|I|Final Result Ru|I<CR>
C|2|I|Above Check|I<CR>
R|3|^^^aHBs2^^^1^DOSE|319.36|mIU/mL||||F\R||||20220126105354|IRL28542117<CR>
C|1|I|Final Result Ru|I<CR>
C|2|I|Above Check|I<CR>
R|4|^^^aHBs2^^^1^INTR|React|||||F\R<ETB>39<CR><LF>');
		socket_write($socket, $output);

		$output = replacedata('<STX>3||||20220126105354|IRL28542117<CR>
C|1|I|Final Result Ru|I<CR>
C|2|I|Above Check|I<CR>
L|1|N<CR>
<ETX>27<CR><LF>');
		socket_write($socket, $output);


		socket_write($socket, replacedata('<EOT>'));
	}
	else if(isset($argv[2])&&$argv[2]=='t'){
		socket_write($socket, buildpk_astm('client_ip|173.167.230.33'));
		socket_write($socket,chr(EOT).chr(SOH).chr(LF));


		exit;
		$astm_num = 0;

		///*
		socket_write($socket, buildpk_astm('H|\^&|||igniteLIS|||||UIW_LIS||P||20220307113831<CR>
P|1|346103|||Test^Pt^||19921025|F<CR>
O|1|ION12389323||^^^aHBcM|R|20220307113831|||||N||||<CR>
L|1|N<CR>'));//*/

		//flush buf
		socket_write($socket,chr(SOH).chr(LF));

		//socket_write($socket,chr(EOT).chr(SOH).chr(LF));

	}
	else{

		socket_write($socket, buildpk_astm('H|\^&|||UIW_LIS|||||igniteLIS||P||20220303142411<CR>
Q|1|^ION12389323|^ION12389323|ALL||||||||O<CR>
L|1|N<CR>'));

		socket_write($socket, replacedata('<EOT>'));

		echo "Reading response:\n\n";
		$has_ETX = false;
		$last_buf = '';
		while (($buf = socket_read($socket, 1))!==false) {

			echo replacecode($buf);

			if($buf==chr(ETX))
			{
				$has_ETX = true;
			}

			if(strlen($buf)==0)
			{
				echo "\n";
				echo 'empty buf';
				echo "\n";
				break;
			}
			else if($has_ETX && $buf==chr(LF))
			{
				socket_write($socket, chr(ACK));
				break;
			}else if($last_buf==chr(CR) && $buf==chr(LF)){
				socket_write($socket, chr(ACK));
				echo "\n";
				echo "sent ACK";
				echo "\n";
			}


			$last_buf = $buf;

		}
	}


	echo "\n";
	echo "Closing socket...";
	socket_close($socket);
	echo "OK.\n\n";

}
else if(isset($_GET['pho']) || $argv[1]=='pho'){


	/* Get the port for the WWW service. */
	$service_port = isset($_GET['port']) ? $_GET['port'] : 8681;

	/* Get the IP address for the target host. */
	$address = isset($_GET['ip']) ? $_GET['ip'] : '127.0.0.1';

	/* Create a TCP/IP socket. */
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket === false) {
		echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
	} else {
		echo "OK.\n";
	}

	echo "Attempting to connect to '$address' on port '$service_port'...";
	$result = socket_connect($socket, $address, $service_port);
	if ($result === false) {
		echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
	} else {
		echo "OK.\n";
	}


	if(isset($argv[2]) && $argv[2]=='rs'){

		socket_write($socket, replacedata('<EOT>'));
	}
	else{

		socket_write($socket, replacedata('<STX><ACK><ETX>'));
		var_dump(replacedata('<STX><ACK><ETX>'));
		exit;


		echo "Reading response:\n\n";
		$has_ETX = false;
		$last_buf = '';
		while (($buf = socket_read($socket, 1))!==false) {

			echo replacecode($buf);

			if($buf==chr(ETX))
			{
				$has_ETX = true;
			}

			if(strlen($buf)==0)
			{
				echo "\n";
				echo 'empty buf';
				echo "\n";
				break;
			}
			else if($has_ETX && $buf==chr(LF))
			{
				socket_write($socket, chr(ACK));
				break;
			}else if($last_buf==chr(CR) && $buf==chr(LF)){
				socket_write($socket, chr(ACK));
				echo "\n";
				echo "sent ACK";
				echo "\n";
			}


			$last_buf = $buf;

		}
	}


	echo "\n";
	echo "Closing socket...";
	socket_close($socket);
	echo "OK.\n\n";

}