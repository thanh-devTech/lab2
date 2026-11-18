<?php
global $wpdb;

$groups = array(
	'uti' => 'UTI',
	'wound' => 'Wound',
	'gi' => 'GI'
);

$act = isset($_REQUEST['act'])? $_REQUEST['act']:'';

if(isset($_GET['fix_gram'])){
	$rs = $wpdb->get_results("select * from ci_bacteria where Gram = 0");

	foreach($rs as $r){
		$cat = $wpdb->get_var("select tg.tg_name from ci_test_groups tg
                                        join ci_test_panels te ON te.te_group_id = tg.tg_ID
                                        where te.te_name = '{$r->Name}' and tg.tg_name like '%gram%'
                                        and te.te_type='pcr'");
		var_dump($cat);
		var_dump($wpdb->last_error);
		if(stripos($cat,'Positive')>0)
		{
			$wpdb->update('ci_bacteria',array('Gram'=>'1'),array('ID'=>$r->ID));
		}
	}

}

if($act=='import_bacteria' && !empty($_FILES['bacteria_file']['name']))
{
    $groupk         = isset($_REQUEST['groupk'])? $_REQUEST['groupk']:'uti';
	$bacteria_tbl   = trpdd_get_bacteria_table($groupk);

	$data           = trpdd_get_data($_FILES['bacteria_file']['tmp_name'],'csv',array('name'),true);

	if(count($data['data'])>0)
	{
		$wpdb->query("drop table {$bacteria_tbl} ");
		$sql = "CREATE TABLE `{$bacteria_tbl}` (
                      `ID` int(11) NOT NULL AUTO_INCREMENT,
                      `Name` varchar(100) DEFAULT NULL,
                      `Description` text DEFAULT NULL,
                      `Base Antibiotics` text ,
                      `Gram` tinyint(1) DEFAULT NULL,
                      `QRCode` varchar(40) DEFAULT NULL,
                      PRIMARY KEY (`ID`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
		$rs = $wpdb->query($sql);

	}
	$rs = $wpdb->get_results("show COLUMNS from {$bacteria_tbl}");
	$cols = array();
	foreach($rs as $col)
	{
		$colname = $col->Field;
		$cols[strtolower($colname)] = $colname;
	}
	$count_insert = 0;
	$count_update = 0;
	foreach($data['data'] as $row)
	{
		$rowdata = array();
		$has_data = false;
		$row['name'] = trim($row['name']);

		foreach($row as $k => $vl)
		{
			if(empty($k)){
				continue;
			}
			$colname = isset($cols[$k])? $cols[$k] :'';
			if(empty($colname))
			{
				$colname = ucfirst($k);
				$cols[$k] = $colname;

				$wpdb->query("alter table {$bacteria_tbl} add column `{$colname}` text");
			}

			$rowdata[$colname] = $row[$k];
			if(!empty($row[$k]) && $colname!='ID')
			{
				$has_data = true;
			}
		}
		if(!$has_data)
		{
			continue;
		}

		foreach($cols as $k => $colname)
		{
			if(empty($k)){
				continue;
			}
			if(!isset($rowdata[$colname])){
				$rowdata[$colname] = '';
			}
		}
		unset($rowdata['ID']);

		$exist = $wpdb->get_var("select ID from {$bacteria_tbl} where Name='{$row['name']}'");
		if($exist)
		{
			$wpdb->update($bacteria_tbl,$rowdata,array('ID'=>$exist));
			$count_update++;
		}else{
			$rsi = $wpdb->insert($bacteria_tbl,$rowdata);
			if($rsi!==false)
			{
				$count_insert++;
			}else{
				var_dump($wpdb->last_error);
				var_dump($rowdata);
			}
			
		}
	}
	Tr_Session_Class::add('Inserted: '.$count_insert);
	Tr_Session_Class::add('Updated: '.$count_update);

}

if($act=='import_abx' && !empty($_FILES['abx_file']['name'])){

	$groupk         = isset($_REQUEST['groupk'])? $_REQUEST['groupk']:'uti';

	$abx_tbl        = trpdd_get_abx_table($groupk);

	$data           = trpdd_get_data($_FILES['abx_file']['tmp_name'],'csv',array('marker'),true);

	if(count($data['data'])>0)
	{
		$wpdb->query("drop table {$abx_tbl} ");

		$sql = "CREATE TABLE `{$abx_tbl}` (
                      `ID` int(11) NOT NULL AUTO_INCREMENT,
                      `Marker` varchar(100) DEFAULT NULL,
                      `Description` varchar(450) NOT NULL,
                      `Resistant` varchar(450) DEFAULT NULL,
                      `Sensitivity` varchar(560) DEFAULT NULL,
                      `HarmfulAntibiotics` varchar(40) DEFAULT NULL,
                      `BadAntibiotics` varchar(40) DEFAULT NULL,
                      PRIMARY KEY (`ID`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

		$rs = $wpdb->query($sql);

	}

	$rs = $wpdb->get_results("show COLUMNS from {$abx_tbl}");
	$cols = array();
	foreach($rs as $col)
	{
		$colname = $col->Field;
		$cols[strtolower($colname)] = $colname;
	}
	$count_insert = 0;
	$count_update = 0;
	foreach($data['data'] as $row)
	{
		$rowdata = array();
		$has_data = false;
		$row['marker'] = trim($row['marker']);

		foreach($row as $k => $vl)
		{
			$colname = isset($cols[$k])? $cols[$k] :'';
			if(empty($colname))
			{
				$colname = ucfirst($k);
				$cols[$k] = $colname;

				$wpdb->query("alter table {$abx_tbl} add column `{$colname}` text");
			}

			$rowdata[$colname] = $row[$k];
			if(!empty($row[$k]) && $colname!='ID')
			{
				$has_data = true;
			}
		}

		if(!$has_data)
		{
			continue;
		}


		foreach($cols as $k => $colname)
		{
			if(!isset($rowdata[$colname])){
				$rowdata[$colname] = '';
			}
		}

		unset($rowdata['ID']);


		$exist = $wpdb->get_var("select ID from {$abx_tbl} where Marker='{$row['marker']}'");
		if($exist)
		{
			$wpdb->update($abx_tbl,$rowdata,array('ID'=>$exist));
			$count_update++;
		}else{
			$wpdb->insert($abx_tbl,$rowdata);
			$count_insert++;
		}
	}
	Tr_Session_Class::add('Inserted: '.$count_insert);
	Tr_Session_Class::add('Updated: '.$count_update);

}
?>
<div class="wrap">
    <h2>Import & Export</h2>
	<?php Tr_Session_Class::show()?>

	<?php
	foreach($groups as $groupk => $groupt)
	{
		$bacteria_tbl   = trpdd_get_bacteria_table($groupk);
		$abx_tbl        = trpdd_get_abx_table($groupk);
		?>
        <div style="border:1px solid #ddd;padding:0 20px;margin-bottom:40px;background:#fff">
            <h2 class="center" style="margin-top:5px;position: relative;"><?php echo $groupt?></h2>
            <div style="float:left;width:47%;border:1px solid #ddd">
                <div class="center bold">Bacteria <small>(<?php echo $wpdb->get_var("select count(*) from {$bacteria_tbl}");?>)</small></div>
                <form method="post" enctype="multipart/form-data" style="margin:10px;text-align: center">

                    <input type="file" name="bacteria_file" accept="text/csv"/>

                    <input type="hidden" name="act" value="import_bacteria"/>
                    <input type="hidden" name="groupk" value="<?php echo $groupk?>"/>

                    <input type="submit" class="button" value="Import"/>
                    <a href="<?php Tr_Base_Class_V6::link(array('act'=>'export_bacteria','trajax'=>1,'groupk'=>$groupk))?>" class="button">Export </a>

                </form>
            </div>
            <div style="float:right;width:47%;border:1px solid #ddd">
                <div class="center bold">ABX <small>(<?php echo $wpdb->get_var("select count(*) from {$abx_tbl}");?>)</small></div>
                <form method="post" enctype="multipart/form-data" style="margin:10px;text-align: center">
                    <input type="file" name="abx_file" accept="text/csv"/>
                    <input type="hidden" name="act" value="import_abx"/>
                    <input type="hidden" name="groupk" value="<?php echo $groupk?>"/>

                    <input type="submit" class="button" value="Import"/>
                    <a href="<?php Tr_Base_Class_V6::link(array('act'=>'export_abx','trajax'=>1,'groupk'=>$groupk))?>" class="button">Export</a>

                </form>
            </div>
            <div class="frow">&nbsp;</div>
        </div>

		<?php
	}
	?>

</div>