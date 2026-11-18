<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

class DB
{
	public $connect     = null;
	public $connected   = false;
	public $config      = null;
	public $last_query  = '';
	public $last_drug   = '';
	public $last_id	 = 0;
	public $last_conc   = '';
	public $last_error  = '';

	public $testids = array();
	public $last_testid = 0;
	public $old_conc = '';
	public $cache_tests = array();
	public $client_id  = 0;
	public $insert_id  = 0;
	public $field_types = array();

	function __construct($config, $client_id = 0)
	{
		$this->config = $config;
		$this->client_id = $client_id;
	}

	function __destruct()
	{
		$this->disconnect();
	}

	function connect()
	{
		$this->connected = false;
		try {
			$this->connect = mysqli_init();
			$flag = MYSQLI_CLIENT_COMPRESS;
			if (strpos(__DIR__, 'bitnami') !== false || strpos(__DIR__, 'var/www/html') !== false) {
				$flag = MYSQLI_CLIENT_SSL;
			}
			if (!mysqli_real_connect($this->connect, $this->config['host'], $this->config['user'], $this->config['pass'], $this->config['name'], null, null, $flag)) {
				echo "Connect to DB failed\n";
				return false;
			} else {
				$this->connected = true;
				return true;
			}
		} catch (Exception $e) {
			echo "Connect to DB failed\n";
		}
		return false;
	}

	function disconnect()
	{
		$this->connected = false;
		try {
			try {
				if ($this->connect !== null) {
					mysqli_close($this->connect);
				}
			} catch (Exception $e) {
			}
		} catch (Error $e2) {
		}

		$this->connect = null;
	}

	function query($query)
	{
		$rs = false;
		$this->last_query = $query;

		try {
			if (!$this->connected) {
				$this->connect();
			}

			$rs = mysqli_query($this->connect, $query);

			if ($rs === false) {
				if ($this->connect()) {
					$rs = mysqli_query($this->connect, $query);
				}
			}
		} catch (Exception $e) {
			try {
				if ($this->connect()) {
					$rs = mysqli_query($this->connect, $query);
				}
			} catch (Exception $e) {
				echo $query . " => ";
				echo "error: " . $e->getMessage() . "\n";
			}
		}
		return $rs;
	}

	function prepare($query, $args)
	{
		if (is_null($query))
			return;

		$args = func_get_args();
		array_shift($args);
		// If args were passed as an array (as in vsprintf), move them up
		if (isset($args[0]) && is_array($args[0]))
			$args = $args[0];
		$query = str_replace("'%s'", '%s', $query); // in case someone mistakenly already singlequoted it
		$query = str_replace('"%s"', '%s', $query); // doublequote unquoting
		$query = preg_replace('|(?<!%)%f|', '%F', $query); // Force floats to be locale unaware
		$query = preg_replace('|(?<!%)%s|', "'%s'", $query); // quote the strings, avoiding escaped strings like %%s
		array_walk($args, array($this, 'escape_by_ref'));
		return vsprintf($query, $args);
	}

	function insert($table, $data, $format = null, $type = 'INSERT')
	{
		if (!in_array(strtoupper($type), array('REPLACE', 'INSERT')))
			return false;
		$this->insert_id = 0;
		$formats = $format = (array) $format;
		$fields = array_keys($data);
		$formatted_fields = array();
		foreach ($fields as $field) {
			if (!empty($format))
				$form = ($form = array_shift($formats)) ? $form : $format[0];
			elseif (isset($this->field_types[$field]))
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$formatted_fields[] = $form;
		}
		$sql = "{$type} INTO `$table` (`" . implode('`,`', $fields) . "`) VALUES (" . implode(",", $formatted_fields) . ")";

		return $this->query($this->prepare($sql, $data), true);
	}

	function insert_id()
	{
		return mysqli_insert_id($this->connect);
	}

	function update($table, $data, $where)
	{
		$fields = $conditions = $values = array();
		foreach ($data as $field => $value) {

			$fields[] = "`$field` = %s";
			$values[] = $value;
		}

		foreach ($where as $field => $value) {

			$conditions[] = "`$field` = %s";
			$values[] = $value;
		}

		$fields = implode(', ', $fields);
		$conditions = implode(' AND ', $conditions);

		$sql = "UPDATE `{$table}` SET $fields WHERE $conditions";

		return $this->query($this->prepare($sql, $values));
	}

	function fetch_array($query, $result_type = MYSQLI_BOTH)
	{
		return mysqli_fetch_array($query, $result_type);
	}

	function get_var($query)
	{
		$row = null;
		$rs     = $this->query($query);
		if ($rs) {
			$row    = $this->fetch_array($rs, MYSQLI_BOTH);
		}
		return !is_null($row) ? $row[0] : '';
	}

	function get_col($query)
	{
		$arr = array();
		$rs     = $this->query($query);

		while ($result = $this->fetch_array($rs, MYSQLI_BOTH)) {
			$arr[] = $result[0];
		}
		return $arr;
	}

	function get_row($query)
	{
		$row = null;
		$rs     = $this->query($query);
		if ($rs) {
			$row    = $this->fetch_array($rs, MYSQLI_ASSOC);
		}
		return $row;
	}

	function get_results($query)
	{
		$arr = array();
		$rs     = $this->query($query);

		while ($result = $this->fetch_array($rs, MYSQLI_ASSOC)) {
			$arr[] = $result;
		}
		return $arr;
	}

	function batch_table($btype = 'tox')
	{
		$table = 'ci_batches';
		if (!empty($btype) && $btype != 'tox') {
			$table .= '_' . $btype;
		}
		return $table;
	}

	function report_table($btype = 'tox')
	{
		$table = 'ci_reports';
		if (!empty($btype) && $btype != 'tox' && $btype != 'dna' && $btype != 'rpp_gi') {
			$table .= '_' . $btype;
		}
		return $table;
	}

	function result_table($btype = 'tox')
	{
		$table = 'ci_results';
		if (!empty($btype) && $btype != 'tox' && $btype != 'dna' && $btype != 'rpp_gi') {
			$table .= '_' . $btype;
		}
		return $table;
	}

	function get_mid($assen, $btype = 'tox')
	{
		if (empty($assen)) {
			return 0;
		}
		$table = $this->batch_table($btype);
		$sql = "select mid from {$table} where assen='{$assen}'";
		return intval($this->get_var($sql));
	}

	function get_main_lab($assen)
	{
		$slu2w = substr($assen, 0, 2);
		$sql = "select lab_dba_init from ci_labs where lab_dba_init like '{$slu2w}%'";
		return $this->get_var($sql);
	}

	function get_report_id($assen, $btype = 'tox')
	{
		if (empty($assen)) {
			return 0;
		}
		$table = $this->report_table($btype);
		$sql = "select id from {$table} where patient_id ='{$assen}'";
		return intval($this->get_var($sql));
	}



	function get_report($assen, $btype = 'tox')
	{
		if (empty($assen)) {
			return null;
		}
		$table = $this->report_table($btype);
		$sql = "select * from {$table} where patient_id ='{$assen}'";
		return $this->get_row($sql);
	}



	function get_assen_by_id($mid, $btype)
	{
		if (empty($mid)) {
			return '';
		}
		$table = $this->batch_table($btype);
		$sql = "select assen from {$table} where mid ='{$mid}'";
		return $this->get_var($sql);
	}

	function get_qc($assen, $batch_id, $btype = 'tox')
	{
		if (empty($assen)) {
			return null;
		}
		$table = $this->report_table($btype);
		$sql = "select * from {$table} where patient_id ='{$assen}' and batch_id = '{$batch_id}'";
		return $this->get_row($sql);
	}

	function get_lab_init()
	{
		$lab_init = $this->get_var("select lab_dba_init from ci_labs 
							where lab_status = 1 and lab_is_ref in (0,2)
							 order by lab_ID asc limit 1");
		return $lab_init;
	}

	function create_qc($assen, $data = array(), $btype = 'tox')
	{
		if (empty($assen)) {
			return 0;
		}
		$patient_name = isset($data['patient_name']) ? $data['patient_name'] : '';
		$instrument = isset($data['instrument']) ? $data['instrument'] : '';
		$batch_id 	= isset($data['batch_id']) ? $data['batch_id'] : 0;
		$sublab 	= isset($data['sublab']) ? $data['sublab'] : $this->get_lab_init();
		$table 		= $this->report_table($btype);
		$user_id 	= 0;
		if (defined('API_ID')) {
			$user_id = API_ID;
		} else {
			$user_id = IntrumentServer::API_TYPE * -1;
		}
		$created = date('Y-m-d H:i:s');
		$created_date = date('Y-m-d');
		$acquisition_date = isset($data['acquisition_date']) ? $data['acquisition_date'] : date('Y-m-d');
		$sql = "INSERT INTO {$table} (batch_id,patient_id,patient_name,author_id,created,created_date,instrument, acquisition_date, sublab)
                             values ('{$batch_id}','{$assen}','{$patient_name}','{$user_id}','{$created}','{$created_date}','{$instrument}','{$acquisition_date}','{$sublab}') ";


		$this->query($sql);
		$id = mysqli_insert_id($this->connect);
		if (empty($id)) {
			echo 'error query: ' . $sql . "\n";
			$this->last_error = $sql;
		}
		return $id;
	}

	function create_report($assen, $btype = 'tox', $instrument = '')
	{
		if (empty($assen)) {
			return 0;
		}
		$table = $this->report_table($btype);
		$user_id = 0;
		if (defined('API_ID')) {
			$user_id = API_ID;
		} else {
			$user_id = IntrumentServer::API_TYPE * -1;
		}
		$created = date('Y-m-d H:i:s');
		$created_date = date('Y-m-d');
		$sql = "INSERT INTO {$table} (batch_id,patient_id,author_id,created,created_date,instrument)
                             values (0,'{$assen}','{$user_id}','{$created}','{$created_date}','{$instrument}') ";


		$this->query($sql);
		$id = mysqli_insert_id($this->connect);
		if (empty($id)) {
			echo 'error query: ' . $sql . "\n";
			$this->last_error = $sql;
		}
		return $id;
	}

	function update_report($assen, $btype = 'tox', $instrument = '')
	{
		if (empty($assen)) {
			return 0;
		}
		$table = $this->report_table($btype);
		$updatestr = "instrument ='{$instrument}'";
		if ($btype == 'blood') {
			$updatestr .= " , covid_pos = null ";
		} else if ($btype == 'tox') {
			$updatestr .= " , has_reflex = null, has_pos = null ";
		}
		$sql = "Update {$table} set {$updatestr} where patient_id = '{$assen}' ";
		return $this->query($sql);
	}

	function reverse_report($assen, $btype)
	{
		if (empty($assen)) {
			return 0;
		}
		$table = $this->report_table($btype);
		$sql = "Update {$table} set estatus = 'M' where patient_id = '{$assen}' and estatus = 'C' ";
		return $this->query($sql);
	}

	/*
	$arr = array(
		'drug' => 'drug',
		'conc'=> conc,
		'key' => 'columnname'
	);
	*/

	function insert_result_urine($mid, $arr)
	{
		if (count($this->testids) == 0) {
			$this->testids = $this->get_all_tests_ids('tox', $arr['key']);
		}
		$arr['drug'] 	= strtoupper(trim($arr['drug']));
		$testarr 		= isset($this->testids[$arr['drug']]) ? $this->testids[$arr['drug']] : array();


		$conc 			= $arr['conc'];
		$api_id 		= $arr['api'];

		if (!isset($testarr['ID'])) {
			$this->last_testid = 0;
			$this->last_query = 'no Test ID for "' . $arr['drug'] . '"';
			return false;
		}

		$this->last_testid = $testarr['ID'];

		$sql = "INSERT INTO ci_result_test_speciments (mid,testid,conc,api_type)
				values ('{$mid}','{$testarr['ID']}','{$conc}','" . $api_id . "')
				ON DUPLICATE KEY UPDATE conc = '{$conc}', api_type = " . $api_id;

		return $this->query($sql);
	}

	function insert_result($report_id, $arr, $btype)
	{
		$this->last_testid = 0;
		if ($report_id == 0) {
			return false;
		}
		$drug           = $arr['drug'];
		$conc           = $arr['conc'];
		$instrument     = $arr['instrument'];
		$key			= isset($arr['key']) ? $arr['key'] : 'te_abrev';
		$speciment_type = isset($arr['speciment_type']) ? $arr['speciment_type'] : '';
		$testids		= isset($arr['testids']) ? implode(',', $arr['testids']) : '';

		if (isset($arr['centaur_code']) && !empty($arr['centaur_code'])) {
			$key = 'te_centaur_code';
		} else if (isset($arr['au680']) && !empty($arr['au680'])) {
			$key = 'te_au680';
		}

		$list_drugs 	= array();
		$list_drugs[] 	= array(
			'drug' => $drug,
			'decimal' => -1
		);

		if ($btype == 'study') {

			$tests = $this->get_all_ins_study_tests($instrument, $key);
			$drug_low = strtolower($drug);
			if (isset($tests[$drug_low])) {
				$list_drugs = array();
				$list_drugs[] = array(
					'drug' => $tests[$drug_low]['drug'],
					'group_id' => $tests[$drug_low]['ID'],
					'decimal' => -1
				);
			}
		} else {

			$ttype_ids = array();
			if (!empty($speciment_type)) {
				$ttype_ids = $this->get_col("select ts_ID from ci_test_specimens 
							where ts_name like '%{$speciment_type}%' 
							and ts_type='{$btype}'");
			}



			$sql_df		= "select 
			te_ID, 
			te_name,
			te_abrev,
			te_conc_decimal,
			te_group_id
			from ci_test_panels 
			where {$key}='{$drug}' 
			and te_type='{$btype}'";

			if (count($ttype_ids) > 0) {
				$ttype_ids_str = implode(',', $ttype_ids);
				$sql_df .= " and te_type_id in ({$ttype_ids_str}) ";
			}

			$sql_df2 = '';
			if (!empty($testids)) {
				$sql_df2 =  $sql_df . " and te_ID in ({$testids}) ";
			}

			$sqls = array();
			if (isset($arr['is_urine'])) {
				$where_urine = '';
				if ($arr['is_urine']) {
					$where_urine = " and (te_name like '%urine%' or te_name like '%RRR%') ";
				} else {
					$where_urine = " and (te_name not like '%urine%' and te_name not like '%RRR%') ";
				}

				if (!empty($sql_df2)) {
					$sqls[] = $sql_df2 . $where_urine;
				}

				$sqls[] = $sql_df . $where_urine;
			}

			if (!empty($sql_df2)) {
				$sqls[] = $sql_df2;
			}
			$sqls[] = $sql_df;

			foreach ($sqls as $sql) {
				$testdbs = $this->get_results($sql);
				if (!empty($testdbs)) {
					break;
				}
			}

			if (!empty($testdbs)) {
				$list_drugs = array();
				foreach ($testdbs as $testdb) {
					$decimal = -1;
					if (is_null($testdb['te_conc_decimal']) || $testdb['te_conc_decimal'] === '') {
						$testdb['te_conc_decimal'] = -1;
					}

					if ($testdb['te_conc_decimal'] > -1) {
						$decimal    = $testdb['te_conc_decimal'];
					}

					$list_drugs[] 	= array(
						'drug' => $testdb['te_name'],
						'abrev' => $testdb['te_abrev'],
						'decimal' => $decimal,
						'group_id' => $testdb['te_group_id']
					);
					$this->last_testid = $testdb['te_ID'];
				}
			}
		}


		$table  = $this->result_table($btype);
		$rs 	= false;
		foreach ($list_drugs as $drugarr) {

			$drug 		= $drugarr['drug'];
			$decimal 	= $drugarr['decimal'];
			$group_id   = intval(isset($drugarr['group_id']) ? $drugarr['group_id'] : 0);

			$conc_formated 	= $conc;
			if ($decimal >= 0 && !in_array(substr($conc, 0, 1), array('<', '>'))) {
				try {
					$conc_formated = number_format(floatval($conc), $decimal);
				} catch (Exception $e) {
					$conc_formated = $conc;
				}
			}

			$this->last_drug = $drug;
			$this->last_conc = $conc_formated;
			$this->old_conc = '';
			$this->last_id = 0;

			$lastdata     = $this->get_row("select * from {$table} 
							where report_id = {$report_id} 
							and drug='{$drug}'
							and group_id = '{$group_id}' ");

			if (empty($lastdata)) {

				$lastdata     = $this->get_var("select * from {$table} 
							where report_id = {$report_id} and drug='{$drug}'
							and (group_id = 0 or group_id is null) ");
			}



			if (empty($lastdata)) {
				$fields = "report_id,drug,conc,group_id";
				$values = "'{$report_id}','{$drug}','{$conc_formated}','{$group_id}'";
				if (!empty($instrument)) {
					$fields .= " , instrument ";
					$values .= " , '{$instrument}' ";
				}
				$sql = "INSERT INTO {$table} ({$fields}) values ({$values}) ";
				$rs = $this->query($sql);
			} else {
				$id = intval($lastdata['id']);

				$this->old_conc = $lastdata['conc'];
				$this->last_id = $id;
				$update = "conc = '{$conc_formated}' ,";
				$update .= "group_id = '{$group_id}' ";
				if (!empty($instrument)) {
					$update .= " , instrument = '{$instrument}' ";
				}
				$sql = "update {$table} set {$update} 
								where id = {$id} ";
				$rs = $this->query($sql);
			}
		}


		return $rs;
	}

	function get_all_ins_study_tests($instrument, $drugkey)
	{
		global $cache_tests;

		if (isset($cache_tests['study']) && !empty($cache_tests['study'])) {
			return $cache_tests['study'];
		}
		$col_key = 'sysmex_xn_530';
		if ($drugkey == 'te_phoresis') {
			$col_key = 'phoresis';
		}

		$cache_tests['study'] = array();

		$sql = "select * from ci_study_tests
	            where  {$col_key} = 1 ";

		$rs = $this->get_results($sql);
		foreach ($rs as $r) {

			$r['reported_values'] = json_decode($r['reported_values'], true);
			foreach ($r['reported_values'] as $arr) {
				if (!empty($arr['v'])) {
					$cache_tests['study'][strtolower($arr['i'])] = array(
						'ID' => $r['ID'],
						'drug' => $arr['v']
					);
				}
			}
		}
		return $cache_tests['study'];
	}

	function get_batch_data($assen, $btype = 'tox')
	{
		$table = $this->batch_table($btype);
		$sql = "select ba.* , 
	        pa.pa_first,
	        pa.pa_last,
	        pa.pa_middle,
	        pa.pa_dob, 
	        pa.pa_gender,
			doc.doc_last,
			doc.doc_first
	        from {$table} ba
	     left join ci_patients pa On pa.pa_ID = ba.patient_id
		 left JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
	    where ba.assen='{$assen}'";

		return $this->get_row($sql);
	}

	function update_batch_id($assen, $btype, $batchid)
	{
		$table = $this->batch_table($btype);
		$this->query("update {$table} set batch_id='{$batchid}' where assen = '{$assen}' ");
	}

	function get_sample_type($assen, $btype = 'tox')
	{
		$table = $this->batch_table($btype);

		$type = 'X';
		$r = $this->get_var("select type_of_specimen from {$table} where assen='{$assen}'");
		if (stripos($r, 'Serum') !== false) {
			$type = ' ';
		} else if (stripos($r, 'Urine') !== false) {
			$type = 'U';
		} else if (stripos($r, 'blood') !== false) {
			$type = 'W';
		}
		return $type;
	}

	function get_tests($assen, $btype = 'tox', $instrument = '', $withsamtype = false)
	{

		$instrument = strtolower($instrument);

		$batch = $this->get_batch_data($assen, $btype);

		$tests = array();

		if ($btype == 'tox') {
			$drugkey = 'u_abreviation_au680';
			if ($instrument == 'indiko') {
				$drugkey = 'u_abreviation_indiko';
			} else if ($instrument == 'dxc700') {
				$drugkey = 'u_abreviation_dxc700';
			} else if ($instrument == 'diatron500') {
				$drugkey = 'u_abreviation_pic500';
			} else if ($instrument == 'diatron700') {
				$drugkey = 'u_abreviation_pic700';
			}
			//list urine
			$rs = $this->get_results("select {$drugkey} from ci_urine_validity 
                            where {$drugkey} is not null  
                            and {$drugkey}!=''
                             and u_showinreq = 0 
                             and u_status = 1");

			foreach ($rs as $r) {
				$tests[] = $r[$drugkey];
			}

			//list screening
			$rs = $this->get_results("select u.{$drugkey} from ci_urine_validity u 
                    join ci_result_test_speciments t ON t.testid = u.u_ID and t.checked = 1
                    where u.{$drugkey} is not null and u.{$drugkey}!=''
                    and u.u_showinreq = 1 and u.u_status = 1
                    and t.mid = {$batch['mid']}");


			foreach ($rs as $r) {
				$tests[] = $r[$drugkey];
			}

			//list VDX
			//if($batch['has_vdx'])
			//{
			$rs = $this->get_results("select {$drugkey} from ci_urine_validity 
                            where {$drugkey} is not null  
                            and {$drugkey}!=''
                             and u_showinreq = 2 
                             and u_status = 1");

			foreach ($rs as $r) {
				$tests[] = $r[$drugkey];
			}
			// }
		} else {
			$tids = array();
			$group_ids = array();
			foreach (explode(',', $batch['panel_tests_requested']) as $t) {
				if (stripos($t, 'GROUP_') !== false) {
					$gid = str_ireplace('GROUP_', '', $t);
					if ($gid > 0) {
						$group_ids[] = $gid;
					}
				} else if ($t > 0) {
					$tids[] = $t;
				}
			}
			if (count($tids) > 0) {
				$tids = implode(',', $tids);
			} else {
				$tids = '-1';
			}
			$drugkey = 'te_au680';
			if ($instrument == 'dxc700') {
				$drugkey = 'te_dxc700';
			}

			$where_group = '';
			if (count($group_ids) > 0) {
				$group_ids = implode(',', $group_ids);
				$where_group = " or (
								te.te_group_id in (
		                        	select tg_ID from ci_test_groups 
		                        	where tg_ID in ({$group_ids})
		                        	and (tg_notestcheckbox = 1 or tg_only_group = 1)
								
								)
								and te_in_req = 1
		                    ) ";
			}

			$samtypes = array();

			if ($withsamtype) {
				$rs = $this->get_results("select ts_ID,ts_name from ci_test_specimens 
				where ts_type='{$btype}'");
				foreach ($rs as $r) {
					$r['ts_name'] = strtolower($r['ts_name']);
					$samtypes[$r['ts_ID']] = $r['ts_name'];
					if (stripos($r['ts_name'], 'serum') !== false) {
						$samtypes[0] = $r['ts_name'];
					}
				}

				$sql = "select {$drugkey},te_type_id,te_relations
				from ci_test_panels te
	            where (te.te_ID in({$tids}) {$where_group})
	            and {$drugkey} != '' ";
			} else {
				$sql = "select {$drugkey},te_relations
				from ci_test_panels te
	            where (te.te_ID in({$tids}) {$where_group})
	            and {$drugkey} != '' ";
			}




			$rs = $this->get_results($sql);
			foreach ($rs as $r) {
				$typename = '';

				if ($withsamtype) {
					$r['te_type_id'] = intval($r['te_type_id']);
					$typename = $samtypes[$r['te_type_id']];
					if (!in_array($r[$drugkey], $tests[$typename])) {
						$tests[$typename][] = $r[$drugkey];
					}
				} else if (!in_array($r[$drugkey], $tests)) {
					$tests[] = $r[$drugkey];
				}

				if (!empty($r['te_relations'])) {
					$sql2 = "select {$drugkey},te_relations
					from ci_test_panels te
					where (te.te_ID in({$r['te_relations']}))
					and {$drugkey} != '' ";
					$rs2 = $this->get_results($sql2);
					foreach ($rs2 as $r2) {
						if ($withsamtype) {
							if (!in_array($r2[$drugkey], $tests[$typename])) {
								$tests[$typename][] = $r2[$drugkey];
							}
						} else if (!in_array($r2[$drugkey], $tests)) {
							$tests[] = $r2[$drugkey];
						}
					}
				}
			}
		}



		return $tests;
	}

	function get_tests_specimens($assen, $test_key = '')
	{
		$batch = $this->get_batch_data($assen, 'tox');
		$mid = $batch['mid'];
		if (empty($test_key)) {
			$test_key = 'u_abreviation_architect';
		}

		$tests = array();


		//list urine
		$rs = $this->get_results("select u_name, {$test_key} 
	                        from ci_urine_validity 
                            where {$test_key}  is not null  
                            and {$test_key} !=''
                             and u_showinreq = 0 
                             and u_status = 1");

		foreach ($rs as $r) {
			$tests[$r[$test_key]] = $r['u_name'];
		}

		//list screening
		$rs = $this->get_results("select u.u_name, {$test_key} 
	                from ci_urine_validity u 
                    join ci_result_test_speciments t ON t.testid = u.u_ID and t.checked = 1
                    where u.{$test_key} is not null and u.{$test_key}!=''
                    and u.u_showinreq = 1 and u.u_status = 1
                    and t.mid = {$mid}");

		foreach ($rs as $r) {
			$tests[$r[$test_key]] = $r['u_name'];
		}

		//list VDX
		//if($batch['has_vdx'])
		//{
		$rs = $this->get_results("select u_name, {$test_key} 
	                        from ci_urine_validity 
                            where {$test_key} is not null  
                            and {$test_key}!=''
                             and u_showinreq = 2 
                             and u_status = 1");

		foreach ($rs as $r) {
			$tests[$r[$test_key]] = $r['u_name'];
		}
		// }

		return $tests;
	}

	function get_all_tests_ids($btype = 'tox', $drug = 'u_abreviation_au680', $get_cutoff = false)
	{
		$tests = array();
		if (empty($drug)) {
			$drug = 'u_abreviation';
		}

		if ($btype == 'tox') {
			$column_q = 'u_abreviation';
			if ($drug != 'u_abreviation') {
				$column_q .= ',' . $drug;
			}
			if ($get_cutoff) {
				$column_q .= ",u_low, u_high ";
			}
			$query = "select u_ID,u_name,{$column_q}  from ci_urine_validity where u_status = 1";

			$rs     = $this->query($query);

			while ($result = $this->fetch_array($rs, MYSQLI_ASSOC)) {
				$result[$drug] = strtoupper(trim($result[$drug]));
				if (!empty($result[$drug])) {
					$tests[$result[$drug]] = array(
						'ID' => $result['u_ID'],
						'drug' => $result['u_name'],
					);
				}

				if (isset($result['u_abreviation']) &&  !isset($tests[$result['u_abreviation']])) {
					$result['u_abreviation'] = strtoupper(trim($result['u_abreviation']));
					$tests[$result['u_abreviation']] = array(
						'ID' => $result['u_ID'],
						'drug' => $result['u_name'],
					);
				}
			}
		} else {
			$query = "select te_ID, te_au680,te_name from ci_test_panels where te_type='{$btype}'
						and te_au680 !='' and te_au680 is not null";
			$rs     = $this->query($query);
			while ($result = $this->fetch_array($rs, MYSQLI_ASSOC)) {
				$result['te_au680'] = strtoupper(trim($result['te_au680']));
				$tests[$result['te_au680']] = array(
					'ID' => $result['te_ID'],
					'drug' => $result['te_name'],
				);
			}
		}

		return $tests;
	}

	function testids_ordered($btype, $assen)
	{
		$table = $this->batch_table($btype);
		$batch = $this->get_batch_data($assen, $btype);
		$tids  = array();
		foreach (explode(',', $batch['panel_tests_requested']) as $t) {
			if (stripos($t, 'GROUP_') !== false) {
				$gid = str_ireplace('GROUP_', '', $t);
				if ($gid > 0) {
					$group_ids[] = $gid;
				}
			} else if ($t > 0) {
				$tids[] = $t;
			}
		}
		return $tids;
	}

	function get_all_tests_ids_architect()
	{
		$arr = array();
		$query = "select u_ID, u_abreviation_architect from ci_urine_validity";
		$rs     = $this->query($query);
		while ($result = $this->fetch_array($rs, MYSQLI_ASSOC)) {
			$arr[$result['u_abreviation_architect']] = $result['u_ID'];
		}
		return $arr;
	}

	function get_all_blood_drugs()
	{
		$arr = array();
		$query = "select te_ID, te_name,te_abrev, te_alinity_i from ci_test_panels where te_type='blood' and te_alinity_i!=''";
		$rs     = $this->query($query);
		while ($result = $this->fetch_array($rs, MYSQLI_ASSOC)) {
			$arr[$result['te_ID']] = array(
				'name' => $result['te_name'],
				'abrev' => $result['te_abrev'],
				'alinity' => $result['te_alinity_i']
			);
			$arr['alinity_' . $result['te_alinity_i']] = $result['te_name'];
		}
		return $arr;
	}

	function get_next_sample($btype, $api_id)
	{
		$table = $this->batch_table($btype);
		$today = date('Y-m-d H:i:s', time() - 3600 * 6);
		$query = "select 
						ba.mid,
						ba.patient_id, 
						ba.assen, 
						ba.patient_name,
						ba.panel_tests_requested
					from {$table} ba
					left join ci_sync_api_orders ao ON ao.mo_mid = ba.mid 
							and ao.mo_btype = '{$btype}' and ao.mo_file_id = '{$api_id}'
					where (doc >='{$today}' or batch_created >='{$today}' or batch_modified >= '{$today}')
					and cli_ID > 0
					and patient_id > 0
					and batch_id >0
					and (ao.mo_status is null or ao.mo_status = 0)
					and assen != ''
					and panel_tests_requested!=''
					and panel_tests_requested is not null
				 order by mid asc
				 limit 1";

		return $this->get_row($query);
	}

	function get_tests_study($requested, $instrument = '', $drugkey = '')
	{

		$abreviations	= array();
		$col_key = 'sysmex_xn_530';
		if ($drugkey == 'te_phoresis') {
			$col_key = 'phoresis';
		}
		$testsids = array();
		foreach (explode(',', $requested) as $tid) {
			if ($tid > 0) {
				$testsids[] = $tid;
			}
		}
		$tests_str = implode(',', $testsids);

		$sql = "select * from ci_study_tests
	            where  {$col_key} = 1 
				and ID in ({$tests_str})";

		$rs = $this->get_results($sql);
		foreach ($rs as $r) {

			$r['reported_values'] = json_decode($r['reported_values'], true);
			foreach ($r['reported_values'] as $arr) {
				if (!empty($arr['v'])) {
					$abreviations[] = $arr['i'];
				}
			}
		}
		return $abreviations;
	}

	function get_abreviations_sample($btype, $requested = '', $instrument = '', $drugkey = '', $speciment_type = '')
	{
		if (empty($drugkey)) {
			$drugkey = 'te_abrev';
		}
		$abreviations 	= array();
		$tests 			= explode(',', $requested);
		$tids 			= array();
		$group_ids 		= array();
		$where_group 	= '';

		if ($btype == 'study') {
			return $this->get_tests_study($requested, $instrument, $drugkey);
		}


		foreach ($tests as $t) {
			if (stripos($t, 'GROUP_') !== false) {
				$gid = str_ireplace('GROUP_', '', $t);
				if ($gid > 0) {
					$group_ids[] = $gid;
				}
			} else if ($t > 0) {
				$tids[] = $t;
			}
		}
		if (count($tids) > 0) {
			$tids = implode(',', $tids);
		} else {
			$tids = '-1';
		}


		if (count($group_ids) > 0) {
			$group_ids = implode(',', $group_ids);
			$where_group = " or te.te_group_id in (
		                        select tg_ID from ci_test_groups 
		                        where ( tg_ID in ({$group_ids})  or tg_parent in ({$group_ids}) )
		                        and tg_notestcheckbox = 1
		                    ) ";
		}
		$keyabrevmiss = '';
		if ($drugkey != 'te_abrev') {
			//$keyabrevmiss= ', te_abrev';
		}
		$sql = "select {$drugkey} {$keyabrevmiss} from ci_test_panels te
	            where (te.te_ID in({$tids}) {$where_group})
	            and te_type = '{$btype}' ";

		if (!empty($speciment_type)) {
			$ttype_ids = $this->get_col("select ts_ID from ci_test_specimens where ts_name like '%{$speciment_type}%' and ts_type='{$btype}'");
			if (count($ttype_ids) > 0) {
				$ttype_ids_str = implode(',', $ttype_ids);
				$sql .= " and te_type_id in ({$ttype_ids_str}) ";
			}
		}

		if (!empty($instrument) && $drugkey == 'te_abrev') {
			$sql .= " and te_ID in (select cu_te_ID from ci_test_panels_cutoffs where cu_ms='{$instrument}')";
		}

		$rs = $this->get_results($sql);
		foreach ($rs as $r) {
			if (empty($r[$drugkey])) {
				//$r[$drugkey] = $r['te_abrev'];
			}
			if (!empty($r[$drugkey]) && !in_array($r[$drugkey], $abreviations)) {
				$abreviations[] = $r[$drugkey];
			}
		}
		return $abreviations;
	}

	function get_alinity_i_number_sample($btype, $requested = '')
	{
		$abreviations = array();
		$tests = explode(',', $requested);
		$tids = array();
		$group_ids = array();
		foreach ($tests as $t) {
			if (stripos($t, 'GROUP_') !== false) {
				$gid = str_ireplace('GROUP_', '', $t);
				if ($gid > 0) {
					$group_ids[] = $gid;
				}
			} else if ($t > 0) {
				$tids[] = $t;
			}
		}
		if (count($tids) > 0) {
			$tids = implode(',', $tids);
		} else {
			$tids = '-1';
		}
		$where_group = '';
		if (count($group_ids) > 0) {
			$group_ids = implode(',', $group_ids);
			$where_group = " or te.te_group_id in (
		                        select tg_ID from ci_test_groups 
		                        where tg_ID in ({$group_ids})
		                        and tg_notestcheckbox = 1
		                    ) ";
		}
		$sql = "select te_alinity_i from ci_test_panels te
	            where (te.te_ID in({$tids}) {$where_group})
	            and te_alinity_i != '' ";


		$rs = $this->get_results($sql);
		foreach ($rs as $r) {
			$abreviations[] = $r['te_alinity_i'];
		}
		return $abreviations;
	}

	function set_sent_sample($btype, $mid, $api_id, $datetime = '')
	{
		if (empty($datetime)) {
			$datetime = date('Y-m-d H:i:s');
		}
		$this->query("insert into ci_sync_api_orders
						set mo_mid = '{$mid}',
							mo_btype = '{$btype}', 
							mo_file_id = '{$api_id}',
							mo_status = 1 ,
							mo_control_ID = '{$datetime}'
					ON DUPLICATE KEY UPDATE mo_status = 1, mo_control_ID = '{$datetime}'");
	}

	function set_error_sample($btype, $mid, $error, $api_id, $datetime = '')
	{
		if (empty($datetime)) {
			$datetime = date('Y-m-d H:i:s');
		}
		$this->query("insert into ci_sync_api_orders
						set mo_mid = '{$mid}',
							mo_btype = '{$btype}', 
							mo_file_id = '{$api_id}',
							mo_status = -1 ,
							mo_control_ID = '{$datetime}',
							mo_peg_ID = '{$error}'
					ON DUPLICATE KEY UPDATE mo_status = -1,mo_peg_ID = '{$error}', mo_control_ID = '{$datetime}'");
	}


	function set_received_result($btype, $mid, $api_id, $logdata = array(), $datetime = '')
	{
		try {
			if (empty($datetime)) {
				$datetime = date('Y-m-d H:i:s');
			}
			if (is_array($logdata)) {
				$logdata = json_encode($logdata);
				$logdata = mysqli_escape_string($this->connect, $logdata);
			}
			$sql = "insert into ci_sync_api_orders
						set mo_mid = '{$mid}',
							mo_btype = '{$btype}', 
							mo_file_id = '{$api_id}',
							other_info = '{$logdata}',
							mo_test_ID = 1,
							mo_set_ID = '{$datetime}'
					ON DUPLICATE KEY UPDATE mo_test_ID = 1,mo_set_ID = '{$datetime}', other_info = '{$logdata}' ";

			$this->query($sql);
		} catch (Error $e) {
		}
	}

	function get_unsent_orders($btype, $api_id, $batch_id = '', $limit  = 1)
	{
		$table = $this->batch_table($btype);
		$query = "select *
				from {$table} ba
	     		 join ci_patients pa On pa.pa_ID = ba.patient_id
				 left join ci_sync_api_orders mo 
						ON mo_mid = ba.mid 
							and mo.mo_btype = '{$btype}' 
							and mo.mo_file_id = '{$api_id}'
					where ba.cli_ID > 0
					and ba.patient_id > 0
					and ba.batch_id = '{$batch_id}'
					and (mo.mo_status is null or mo.mo_status = 0)
					order by ba.mid asc
					limit {$limit}";
		return $this->get_results($query);
	}
}
