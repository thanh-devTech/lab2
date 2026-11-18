<?php
$below_drugs = array("HYDROCODONE",
    "HYDROMORPHONE",
    "CODEINE",
    "OXYCODONE",
    "OXYMORPHONE",
    "ALPRAZOLAM",
    "HYDROXYALPRAZOLAM",
    "7-AMINOCLONAZEPAM",
    "6-MAM",
    "BURPENORPHINE",
    "NORBUPRENORPHINE",
    "NORDIAZEPAM",
    "OXAZEPAM",
    "TEMAZEPAM");

/*
    } elseif($arr['anticip']=="CODEINE" || @in_array("CODEINE",$arr['anticip'])) {
        if($all_res['MORPHINE']['comments']=="Detected below cutoff") $arr['comments'] = "";
        if(empty($all_res['MORPHINE']['conc'])) $arr['comments'] = "";
        if((int)$arr['detected'] > 8000) $arr['comments'] = "";
    } elseif($arr['anticip']=="NORBUPRENORPHINE" || @in_array("NORBUPRENORPHINE",$arr['anticip'])) {
        if($all_res['BUPRENORPHINE']['comments']=="Detected below cutoff") $arr['comments'] = "";
        if(empty($all_res['BUPRENORPHINE']['conc'])) $arr['comments'] = "";
*/

$use_below = false;
$use_array = array();
if(is_array($arr['anticip'])) {
    foreach($arr['anticip'] as $an) {
        $test_string = (strpos($an,"(")!==false) ? trim(substr($an,0,strpos($an,"("))) : $an;
        $use_array[] = $test_string;
        if(in_array($test_string,$below_drugs)) $use_below = true;
    }
} else {
    $test_string = (strpos($arr['anticip'],"(")!==false) ? trim(substr($arr['anticip'],0,strpos($arr['anticip'],"("))) : $arr['anticip'];
    $use_array[] = $test_string;
    if(in_array($test_string,$below_drugs)) $use_below = true;
}

if($use_below) {
    if(in_array("BURPENORPHINE",$use_array)) {
        if($all_res['NORBUPRENORPHINE']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the metabolite Norbuprenorphine should be present above cut-off.";
            array_unshift($arr['anticip'],"NORBUPRENORPHINE");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['NORBUPRENORPHINE']);
        }
        if(empty($all_res['NORBUPRENORPHINE']['conc'])) $arr['comments'] = "W/chronic use the metabolite Norbuprenorphine should be detected ";
        if((int)$arr['detected'] > 8000) $arr['comments'] = "With high quantity of Buprenorphine and the absence of a metabolite is suspicious of specimen adulteration by adding drug directly into urine.";
    } elseif(in_array("NORBUPRENORPHINE",$use_array)) {
        if($all_res['BUPRENORPHINE']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/ chronic use. The parent drug Buprenorphine should be present above cut-off.";
            array_unshift($arr['anticip'],"BUPRENORPHINE");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['BUPRENORPHINE']);
        }
        if(empty($all_res['BUPRENORPHINE']['conc'])) $arr['comments'] = "With chronic use the parent drug Buprenorphine should be detected.";
    } elseif(in_array("CODEINE",$use_array)) {
        if($all_res['MORPHINE']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the metabolite Morphine should be present above cut-off.";
            array_unshift($arr['anticip'],"MORPHINE");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['MORPHINE']);
        }
        if(empty($all_res['MORPHINE']['conc'])) $arr['comments'] = "W/chronic use the metabolite Morphine should be detected.";
        if((int)$arr['detected'] > 8000) $arr['comments'] = "With high quantity of Codeine and the absence of a metabolite is suspicious of specimen adulteration by adding drug directly into urine.";
        // Tylenol 3,4 OVERRIDE
        $restore = array("MORPHINE","HYDROCODONE");
        foreach($restore as $r) {
            if(!empty($res_inconsis2[$r])) {
                array_push($arr['anticip'],$r);
                array_push($arr['detected'],$res_inconsis2[$r]['detected']);
                array_push($arr['cutoff'],$cutoffs[$r]);
                unset($res_inconsis2[$r]);
            }
        }
        //
    } elseif(in_array("MORPHINE",$use_array)) {
        if($all_res['CODEINE']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the parent drug Codeine should be present above cut-off.";
            array_unshift($arr['anticip'],"CODEINE");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['CODEINE']);
        }
    } elseif(in_array("HYDROCODONE",$use_array)) {
        if($all_res['HYDROMORPHONE']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the metabolite Hydromorphone should be present above cut-off.";
            array_unshift($arr['anticip'],"HYDROMORPHONE");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['HYDROMORPHONE']);
        }
        if(empty($all_res['HYDROMORPHONE']['conc'])) $arr['comments'] = "W/chronic use the metabolite Hydromorphone should be present above cut-off.";
        if((int)$arr['detected'] > 2000) $arr['comments'] = "With high quantity of Hydrocodone and the absence of a metabolite is suspicious of specimen adulteration by adding drug directly into urine.";
    } elseif(in_array("HYDROMORPHONE",$use_array)) {
        if($all_res['HYDROCODONE']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/ chronic use the parent drug Hydrocodone should be present above cut-off.";
            array_unshift($arr['anticip'],"HYDROCODONE");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['HYDROCODONE']);
        }
    } elseif(in_array("OXYCODONE",$use_array)) {
        if($all_res['OXYMORPHONE']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the metabolite Oxymorphone should be present above cut-off.";
            array_unshift($arr['anticip'],"OXYMORPHONE");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['OXYMORPHONE']);
        }
        if(empty($all_res['OXYMORPHONE']['conc'])) $arr['comments'] = "W/chronic use the metabolite Oxymorphone should be detected.";
        if((int)$arr['detected'] > 2000) $arr['comments'] = "With high quantity of Oxycodone and the absence of a metabolite is suspicious of specimen adulteration by adding drug directly into urine.";
    } elseif(in_array("OXYMORPHONE",$use_array)) {
        if($all_res['OXYCODONE']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the parent drug Oxycodone should be present above cut-off.";
            array_unshift($arr['anticip'],"OXYCODONE");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['OXYCODONE']);
        }
    } elseif(in_array("METHADONE",$use_array)) {
        if($all_res['EDDP']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the metabolite EDDP should be present above cut-off.";
            array_unshift($arr['anticip'],"EDDP");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['EDDP']);
        }
        if((int)$arr['detected'] > 2000) $arr['comments'] = "With high quantity of Methadone and the absence of a metabolite is suspicious of specimen adulteration by adding drug directly into urine.";
    } elseif(in_array("EDDP",$use_array)) {
        if($all_res['METHADONE']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the parent drug Methadone should be present above cut-off.";
            array_unshift($arr['anticip'],"METHADONE");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['METHADONE']);
        }
        if(empty($all_res['METHADONE']['conc'])) $arr['comments'] = "W/chronic use the parent drug Methadone should be detected.";
    } elseif(in_array("FENTANYL",$use_array)) {
        if($all_res['NORFENTANYL']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the metabolite Norfentanyl should be present above cut-off.";
            array_push($arr['anticip'],"NORFENTANYL");
            array_push($arr['detected'],"Detected");
            array_push($arr['cutoff'],$cutoffs['NORFENTANYL']);
        }
        if(empty($all_res['NORFENTANYL']['conc'])) $arr['comments'] = "W/chronic use the metabolite Norfentanyl should be detected.";
        if((int)$arr['detected'] > 1000) $arr['comments'] = "With high quantity of Fentanyl and the absence of a metabolite is suspicious of specimen adulteration by adding drug directly into urine.";
    } elseif(in_array("NORFENTANYL",$use_array)) {
        if($all_res['FENTANYL']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the parent drug Fentanyl should be present above cut-off.";
            array_unshift($arr['anticip'],"FENTANYL");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['FENTANYL']);
        }
    } elseif(in_array("ALPRAZOLAM",$use_array)) {
        if($all_res['HYDROXYALPRAZOLAM']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the metabolite Hydroxyalprazolam  should be present above cut-off.";
            array_push($arr['anticip'],"HYDROXYALPRAZOLAM");
            array_push($arr['detected'],"Detected");
            array_push($arr['cutoff'],$cutoffs['HYDROXYALPRAZOLAM']);
        }
        if(empty($all_res['HYDROXYALPRAZOLAM']['conc'])) $arr['comments'] = "W/chronic use the metabolite Hydroxyalprazolam should be detected.";
        if((int)$arr['detected'] > 2000) $arr['comments'] = "With high quantity of Alprazolam and the absence of a metabolite is suspicious of specimen adulteration by adding drug directly into urine.";
    } elseif(in_array("HYDROXYALPRAZOLAM",$use_array)) {
        if($all_res['ALPRAZOLAM']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/chronic use the parent drug Alprazolam  should be present above cut-off.";
            array_unshift($arr['anticip'],"ALPRAZOLAM");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['ALPRAZOLAM']);
        }
        if(empty($all_res['ALPRAZOLAM']['conc'])) $arr['comments'] = "W/ chronic use the parent drug Alprazolam should be detected.";
    } elseif(in_array("NORDIAZEPAM",$use_array)) {
        if($all_res['OXAZEPAM']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/ chronic use the metabolite Oxazepam should be present above cut-off.";
            array_unshift($arr['anticip'],"OXAZEPAM");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['OXAZEPAM']);
        }
        if($all_res['TEMAZEPAM']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/ chronic use the metabolite Temazepam should be present above cut-off.";
            array_unshift($arr['anticip'],"TEMAZEPAM");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['TEMAZEPAM']);
        }
        if($all_res['TEMAZEPAM']['comments']=="Detected below cutoff" && $all_res['OXAZEPAM']['comments']=="Detected below cut-off") {
            $arr['comments'] = "W/ chronic use the metabolites Oxazepam and Temazepam should be present above cut-off.";
            array_unshift($arr['anticip'],"OXAZEPAM");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['OXAZEPAM']);
            array_unshift($arr['anticip'],"TEMAZEPAM");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['TEMAZEPAM']);
        }
        // Chlordiazepoxide
        if($all_res['OXAZEPAM']['comments']=="Detected below cutoff" && empty($all_res['TEMAZEPAM']['conc'])) {
            $arr['comments'] = "W/chronic use the metabolite Oxazepam should be present above cut-off";
            array_push($arr['anticip'],"OXAZEPAM");
            array_push($arr['detected'],"Detected");
            array_push($arr['cutoff'],$cutoffs['OXAZEPAM']);
        }
    } elseif(in_array("OXAZEPAM",$use_array)) {
        if($all_res['NORDIAZEPAM']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/ chronic use the metabolite Nordiazepam should be present above cut-off.";
            array_unshift($arr['anticip'],"NORDIAZEPAM");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['NORDIAZEPAM']);
        }
        if($all_res['TEMAZEPAM']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/ chronic use the metabolite Temazepam should be present above cut-off.";
            array_push($arr['anticip'],"TEMAZEPAM");
            array_push($arr['detected'],"Detected");
            array_push($arr['cutoff'],$cutoffs['TEMAZEPAM']);
        }
        // Chlordiazepoxide
        if($all_res['NORDIAZEPAM']['comments']=="Detected below cutoff" && empty($all_res['TEMAZEPAM']['conc'])) {
            $arr['comments'] = "W/chronic use the metabolite Nordiazepam and Temazepam should be present above cut-off";
            array_unshift($arr['anticip'],"NORDIAZEPAM");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['NORDIAZEPAM']);
        }

    } elseif(in_array("OXAZEPAM",$use_array)) {
        if($all_res['NORDIAZEPAM']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/ chronic use the metabolite NordiazepamOxazepam should be present above cut-off.";
            array_unshift($arr['anticip'],"NORDIAZEPAM");
            array_unshift($arr['detected'],"Detected");
            array_unshift($arr['cutoff'],$cutoffs['NORDIAZEPAM']);
        }
        if($all_res['OXAZEPAM']['comments']=="Detected below cutoff") {
            $arr['comments'] = "W/ chronic use the metabolite Oxazepam should be present above cut-off.";
            array_push($arr['anticip'],"OXAZEPAM");
            array_push($arr['detected'],"Detected");
            array_push($arr['cutoff'],$cutoffs['OXAZEPAM']);
        }
        // Temazepam
        if($all_res['OXAZEPAM']['comments']=="Detected below cutoff" && empty($all_res['NORDIAZEPAM']['conc'])) {
            $arr['comments'] = "W/chronic use the metabolite Oxazepam should be present above cut-off";
            array_push($arr['anticip'],"OXAZEPAM");
            array_push($arr['detected'],"Detected");
            array_push($arr['cutoff'],$cutoffs['OXAZEPAM']);
            array_push($arr['anticip'],"TEMAZEPAM");
            array_push($arr['detected'],"Detected");
            array_push($arr['cutoff'],$cutoffs['TEMAZEPAM']);
        }
    }
}
?>