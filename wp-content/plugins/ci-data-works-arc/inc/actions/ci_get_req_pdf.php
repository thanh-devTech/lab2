<?php

if(empty($_REQUEST['type']) && strpos($_REQUEST['id'],'_')>0)
{
	list($_REQUEST['type'],$_REQUEST['id']) = explode('_',$_REQUEST['id']);
	$_GET['id'] = $_REQUEST['id'];
}

$_REQUEST['type'] = isset($_REQUEST['btype'])? $_REQUEST['btype'] : $_REQUEST['type'];

$pdf_file = trpdd_get_req_pdf($_REQUEST['id'],$_REQUEST['type']);
$outfile = basename($pdf_file);
if(!isset($_GET['debug'])){
	header('Content-Type: application/pdf');
}
if(!isset($_GET['view'])){
	header('Content-Disposition: attachment; filename="'.basename($outfile).'"');
}


readfile($pdf_file);
@unlink($pdf_file);
exit;