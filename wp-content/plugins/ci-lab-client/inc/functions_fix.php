<?php

function trclient_check_plugin_requireds()
{
	$list = array();
	$list[] = array('name'=>'Dompdf','desc'=>'Dompdf plugin','type' => 'defined','value'=>'TRCDPDF_FILE','status'=>false);
	//$list[] = array('name'=>'Lab Client','desc'=>'Lab Client plugin','type' => 'defined','value'=>'TRLCN_FILE','status'=>false);
	$list[] = array('name'=>'Convert PDF','desc'=>'Convert Pdf to image','type' => 'exec','value'=>'convert help|-','status'=>false);
	$list[] = array('name'=>'GS Lib','desc'=>'Convert Pdf to 1.4 version','type' => 'exec','value'=>'gs help|open','status'=>false);
	$list[] = array('name'=>'mbstring Lib','desc'=>'mbstring Lib','type' => 'function_exists','value'=>'mb_convert_encoding','status'=>false);
	$list[] = array('name'=>'bcmath Lib','desc'=>'bcmath Lib','type' => 'function_exists','value'=>'bcadd','status'=>false);
	$list[] = array('name'=>'XML Lib','desc'=>'XML Lib','type' => 'function_exists','value'=>'simplexml_load_string','status'=>false);
	$list[] = array('name'=>'soap Lib','desc'=>'soap Lib','type' => 'class_exists','value'=>'SoapClient','status'=>false);
	$list[] = array('name'=>'ZipArchive Lib','desc'=>'ZipArchive Lib','type' => 'class_exists','value'=>'ZipArchive','status'=>false);
	$list[] = array('name'=>'SSL Client','desc'=>'SSL Client','type' => 'function','value'=>'trclient_check_ssl','status'=>false);
	$list[] = array('name'=>'Opcache','desc'=>'Opcache','type' => 'function_exists','value'=>'opcache_get_status','status'=>false);
	$list[] = array('name'=>'max_file_uploads','desc'=>'','type' => 'ini_get','value'=>'5','status'=>false);
	$list[] = array('name'=>'memory_limit','desc'=>'','type' => 'ini_get','value'=>'500M','status'=>false);
	$list[] = array('name'=>'upload_max_filesize','desc'=>'','type' => 'ini_get','value'=>'50M','status'=>false);
	$list[] = array('name'=>'max_execution_time','desc'=>'','type' => 'ini_get','value'=>'300','status'=>false);
	$list[] = array('name'=>'max_input_vars','desc'=>'','type' => 'ini_get','value'=>'50000','status'=>false);

	foreach($list as &$arr)
	{
		switch ($arr['type']){
			case 'defined':
				if(defined($arr['value'])){
					$arr['status'] = true;
				}
				break;
			case 'function_exists':
				if(function_exists($arr['value'])){
					$arr['status'] = true;
				}
				break;
			case 'class_exists':
				if(class_exists($arr['value'])){
					$arr['status'] = true;
				}
				break;
			case 'ini_get':
				$rs = ini_get($arr['name']);
				$arr['desc'] = $rs.' ('.$arr['value'].')';
				$arr['value'] = str_replace(array('K','M','G'),'',$arr['value']);
				$rs = str_replace(array('K','M','G'),'',$rs);
				if($rs >= $arr['value']){
					$arr['status'] = true;
				}
				break;
			case 'exec':
				$arr2 = explode('|',$arr['value']);
				$rs = exec($arr2[0]);
				if(stripos($rs,$arr2[1])!==false){
					$arr['status'] = true;
				}
				break;

			case 'function':
				if( call_user_func($arr['value'])){
					$arr['status'] = true;
				}
				break;
		}
	}

	$list = apply_filters('ci_plugin_required',$list);
	return $list;
}

function trclient_check_ssl()
{
	$urlcheck = 'https://www.howsmyssl.com/a/check';
	if(empty(file_get_contents($urlcheck)))
	{
		return false;
	}
	return true;
}

//end of file