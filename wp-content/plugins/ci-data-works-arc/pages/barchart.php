<?php
@error_reporting(E_ERROR);
if(function_exists('date_default_timezone_set'))
    @date_default_timezone_set('America/Los_Angeles');

require_once ('../inc/jpgraph/jpgraph.php');
require_once ('../inc/jpgraph/jpgraph_bar.php');
require_once ('../inc/jpgraph/jpgraph_line.php');



// Some random data to plot
$x = trim($_REQUEST['x'],'|');
$y = trim($_REQUEST['y'],'|');
$title = isset($_REQUEST['tt'])? $_REQUEST['tt'] :'';
if(empty($title))
{
    $title = 'THC-COOH Results';
}

$datax = explode('|',$x);
$datay = explode('|',$y);

$datay = array_map('floatval',$datay);
//$datax[] = '';
if(isset($_GET['debug'])){
    var_dump($datax);
    var_dump($datay);
}



$angle = (count($datay)>6)? 20: 0;

try{

// Create the graph.
    $graph = new Graph(740,280);
    $graph->SetScale("textlin");
    $graph->img->SetMargin(40,30,10,60);
    $graph->xaxis->SetTickLabels($datax);
    $graph->xaxis->SetLabelAngle($angle);

    /*
    // Create a bar pot
    $bplot = new BarPlot($datay);
    //group
    $abplot = new AccBarPlot(array($bplot));
    $graph->Add($abplot);
    $bplot->SetColor(array(0,0,0));
    $bplot->SetFillColor(array(0,0,0));

    $abplot->SetWidth(30);
    $abplot->SetColor(array(0,0,0));
    $abplot->SetFillColor(array(0,0,0));
    $abplot->value->Show();
    */




//line
    $l1plot=new LinePlot($datay);
    if(isset($_GET['debug'])){
        var_dump($l1plot);exit;
    }
    $graph->Add($l1plot);

    $l1plot->mark->SetType(MARK_FILLEDCIRCLE);
    $l1plot->mark->SetWidth(2);
    $l1plot->mark->SetFillColor("black");
    $l1plot->SetColor("#999999");
    $l1plot->SetCenter();
    $l1plot->SetWeight(2);
   // $l1plot->setBarWidth(28);
//$l1plot->SetLegend("Prediction");

//Center the line plot in the center of the bars
    //$l1plot->SetBarCenter();


// Setup the title, also wih a CSIM area
    $graph->title->Set($title);

//$graph->title->SetFont(FF_FONT2,FS_BOLD);
// Assume we can give more details on the graph
//$graph->title->SetCSIMTarget(sprintf($fmtStr,-1),'Title for Bar');


    $graph->Stroke();
}catch (Exception $e)
{
    var_dump($e);
}