<?php

if ($act == 'viewlog') {
	if (empty($_GET['file'])) {
		$_GET['file'] = '';
		foreach ($log['files'] as $file) {
			$_GET['file'] = $file;
			break;
		}
	}
	if (isset($_GET['raw'])) {
		$_GET['file'] = str_replace('log_', 'log_raw', $_GET['file']);
	}
	$logfile = $logpath . '/' . $_GET['file'];
	if (isset($_REQUEST['delete'])) {
		unlink($logfile);
		echo 'Deleted';
		exit;
	}
	echo '<html><body>';
	echo '<pre>';
	echo 'filesize:' . filesize($logfile);
	echo '<br><br>';
	$string = file_get_contents($logfile);
	$string = preg_replace('/[\x14-\x1F\x7F-\xFF]/', '', $string);
	echo htmlentities($string);

	echo '</pre>';
	echo '<div id="endofile"></div>';
?>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script>
		jQuery(function($) {
			$("html, body").animate({
				scrollTop: $(document).height()
			}, 300);
		})
	</script>

	</body>

	</html>
<?php
	exit;
} else if ($act == 'reimport') {
	$logfile = $logpath . '/' . $_GET['file'];
	$onlyqc = isset($_GET['onlyqc']) && $_GET['onlyqc'] ? true : false;

	$data = file_get_contents($logfile);
	$msgs = array();
	if ($ins_type == 'H') {
		$lines = explode('- Parse Message:', $data);
		foreach ($lines as $line) {
			$line = trim($line);
			list($line) = explode('L|1|', $line);
			$line .= 'L|1|F';
			$line = trim($line);
			if (stripos($line, 'H|') === 0) {
				$msgs[] = $line;
			}
		}
	} else if ($ins_type == 'MSH') {
		$lines = explode('<VT>MSH|^~', $data);
		foreach ($lines as $line) {
			$line = 'MSH|^~' . $line;
			$line = trim($line);

			list($line) = explode("<CR>\n<CR>", $line);
			$line .= '<CR>';
			$line = trim($line);
			if ($onlyqc && stripos($line, '||81^') === false) {
				continue;
			}
			if (stripos($line, 'MSH|^~') === 0) {
				$msgs[] = $line;
			}
		}
	}


?>
	<div class="frow" style="display: none;">
		<textarea id="text_send" style="width:70%;height:60px"></textarea>
		<a class="button send_msgbtn">Send</a>

	</div>
	<h5 class="importstatus"><?php echo count($msgs) ?></h5>
	<div class="frow">
		Start at <input type="number" id="startat" value="0" style="width:80px" />
		<a class="button start_import" href="#">Start</a>
		<a class="button clear_logbtn">clear</a>
	</div>
	<div class="frow">
		<div class="col2">
			<textarea id="ws_receving" style="width:100%;height:300px" readonly></textarea>
		</div>
		<div class="col2">
			<textarea id="log_server" style="width:100%;height:300px" readonly></textarea>
		</div>
	</div>
	<script>
		var inow = 0;
		var $msgs = <?php echo json_encode($msgs) ?>;
		var $countall = $msgs.length;
		testclient.connect();
		testclient.cronloadlog();
		testclient.setType('<?php echo $ins_type ?>');

		function runimport() {
			var $msg = $msgs[inow++];
			if (!$msg) return;
			$('#text_send').val($msg);
			$('.send_msgbtn').click();
		}
		$('.send_msgbtn').on('sentmsg', function() {
			console.log('sent');
			$('.importstatus').html(inow + '/' + $countall);
			setTimeout(function() {
				runimport();
			}, 2000);
		})
		$('.start_import').on('click', function() {
			$('.clear_logbtn').click();
			inow = parseInt($('#startat').val());
			runimport();
			$(this).remove();
		})
	</script>
<?php
	exit;
} else if ($act == 'load_log_test_server') {
	$port = isset($_REQUEST['port']) ? $_REQUEST['port'] : $port;
	$return = array('content' => '', 'time' => 0);
	$filepath = $logpath . '/log_' . $port . '_test.txt';
	if (isset($_REQUEST['clear']) && $_REQUEST['clear']) {
		file_put_contents($filepath, '');
	}
	$return['content'] = file_get_contents($filepath);
	$return['time'] = filesize($filepath);
	wp_send_json($return);
	exit;
} else if ($act == 'test') {

?>
	<div class="frow">
		<?php if ($ins_type == 'MSH') { ?>
			<a class="button ws_send" data-msg="MSH|^~\&|Afinion 2 Analyzer||EPR|KH-1|20100610131643||ORU^R01|1048|P|2.4|||AL|NE||8859/1 PID|1|||
PV1|1||||||||||||||||||43|
OBR|1||3|CRP|||||||N||||ORH||||||||^10124809||F| OBX|1|ST|CRP||16|mg/L|||||F|||||||AF0000030|20100608142352|">Result</a>
			<a class="button ws_send" data-msg="<ACK>">ACK</a>

		<?php } else if ($ins_type == 'H') { ?>
			<a class="button ws_send" data-msg="<STX>1H|\^&|||    XN-550^00-11^11090^^^^BD634545||||||||E1394-97<CR><ETX>C9<CR><LF>">Header</a>
			<a class="button ws_send" data-msg="<STX>2Q|1|2^FRL01308327^B||||20160315161239||||||N<CR><ETX>C9<CR><LF>">Request Order</a>
			<a class="button ws_send" data-msg="<STX>3L|1|N<CR><ETX>C9<CR><LF>">L</a>
			<a class="button ws_send" data-msg="<EOT>">EOT</a>
			<a class="button ws_send" data-msg="<ACK>">ACK</a>
		<?php } ?>
	</div>
	<div class="frow">
		<textarea id="text_send" style="width:70%;height:60px"></textarea>
		<a class="button send_msgbtn">Send</a>
		<a class="button clear_logbtn">clear</a>
	</div>
	<div class="frow">
		<div class="col2">
			<textarea id="ws_receving" style="width:100%;height:300px" readonly></textarea>
		</div>
		<div class="col2">
			<textarea id="log_server" style="width:100%;height:300px" readonly></textarea>
		</div>
	</div>
	<script>
		testclient.connect();
		testclient.cronloadlog();
		testclient.setType('<?php echo $ins_type ?>');
	</script>
<?php
	exit;
} else if ($act == 'install') {
	include('install_instrument.php');
	exit;
}
