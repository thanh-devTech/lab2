<?php
$url = get_bloginfo('url').'/wsdl?wsdl';
$client = new SoapClient($url, array(

    'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
    'exceptions' => 1,
    'trace' => 1,
    'soap_version' => SOAP_1_2,
    'cache_wsdl' => WSDL_CACHE_NONE


));

$des = array(
    'put_req' => 'demo.xml',
    'put_blood' => 'blood.xml',
    'put_dna' => 'dna.xml',
    'put_allergy' => 'allergy.xml'
);


foreach($client->__getFunctions() as $f)
{
    preg_match("/\s([^\)]+)\(/",$f,$matches);
    $func = $matches[1];
    preg_match_all("/[\$][a-zA-Z0-9]+/",$f,$matches);
    $args = $matches[0];
    $functions[$func] = array('func'=>$func,'args'=>$args);

}
sort($functions);

foreach($functions as $f)
{
    ?>
    <li>
        <?php echo $f['func'];?>(<?php echo implode(', ',$f['args'])?>)
        <?php
        if(isset($des[$f['func']])){
            ?>
            <br>
            <a href="<?php echo TRCPDD_URL.$des[$f['func']]?>">xml demo</a>
            <?php
        }
        ?>
        <br>
    </li>
    <?php
}