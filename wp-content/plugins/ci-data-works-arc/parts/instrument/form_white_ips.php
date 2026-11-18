<?php
$wip_file = WP_CONTENT_DIR.'/config/whiteips.txt';
if(isset($_POST['whiteips'])){
	$ips = $_POST['whiteips'];
	$ips = str_replace(array(',',';',' ',"\n"),"\r", $ips);
	$ips = str_replace(array("\r\r","\r\n"),"\r",$ips);
	$ips = explode("\r", $ips);
	$ipsarr = array();
	foreach($ips as $ip)
	{
		$ip = trim($ip);
		if(strlen($ip)>8)
		{
			$ipsarr[$ip] = $ip;
		}
		
	}
	$ips = implode("\r", $ipsarr);
	file_put_contents($wip_file, $ips);
}
$whiteips = file_exists($wip_file) ? file_get_contents($wip_file) : '';
?>
<form method="post" style="border:1px dash #ddd;width:400px;margin:20px auto">
	<label>White IPs(all instruments)</label><br>
	<textarea name="whiteips" style="width:150px;height:150px"><?php echo $whiteips?></textarea>
	<br>
	<input type="submit" class="button" value="Save"/>
	
</form>