<?php
define('ABSPATH',dirname(__FILE__));

if($_GET['s']=='img')
{
    require(dirname(__FILE__)."/inc/phpcaptcha.php");

    $aFonts = array(dirname(__FILE__).'/font/VeraBd.ttf');
    
    $oVisualCaptcha = new PhpCaptcha($aFonts, 140, 25);
    
    $oVisualCaptcha->UseColour(true);	 
    
    $oVisualCaptcha->SetNumChars(4);
    
    $oVisualCaptcha->SetMinFontSize(16);
    
    $oVisualCaptcha->SetMaxFontSize(18);
    
    $oVisualCaptcha->Create();
}
