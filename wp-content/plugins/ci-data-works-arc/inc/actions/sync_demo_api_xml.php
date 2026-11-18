<?php
$dir = TRCPDD_PATH.'/demo_xml';
$demodir = ABSPATH.'/demo_xml';

$files = scandir($dir);

foreach($files as $f)
{
	if(stripos($f,'.xml')>0)
	{
		$mtime = filemtime($dir.'/'.$f);
		$dtime = file_exists($demodir.'/'.$f) ? filemtime($demodir.'/'.$f) : 0;
		if($mtime > $dtime)
		{
			file_put_contents($demodir.'/'.$f, file_get_contents($dir.'/'.$f));
			
		}
	}
}