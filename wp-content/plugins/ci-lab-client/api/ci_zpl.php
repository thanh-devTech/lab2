<?php
class CI_Zpl
{
    public function __construct()
    {
        $zpl = "^xa^cfa,50^fo100,100^fdHello World^fs^xz";

        $curl = curl_init();
        // adjust print density (8dpmm), label width (4 inches), label height (6 inches), and label index (0) as necessary
        curl_setopt($curl, CURLOPT_URL, "http://api.labelary.com/v1/printers/8dpmm/labels/2x1/0/");
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $zpl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Accept: image/png")); // omit this line to get PNG images back
        $result = curl_exec($curl);

        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200) {
            header('content-type: image/png');
            echo $result;
        } else {
            print_r("Error: $result");
        }

        curl_close($curl);
    }
}