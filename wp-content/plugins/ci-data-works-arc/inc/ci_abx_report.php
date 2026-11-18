<?php
class CI_ABX_Report
{

	public static function get_bacterias_by_groups($group_gids)
	{
		global $wpdb, $cache_query_seconds;

		if (empty($group_gids)) {
			return array();
		}
		$bacterias = array();
		foreach ($group_gids as $mgid) {

			$sql_bacteria = "select 
			te.te_ID,
			te.te_name,
			te.te_abrev,
			te.te_abrev2,
			te.te_color,
			GROUP_CONCAT(b.ID) as bac_IDs,
			GROUP_CONCAT(b.bacteria_name) as bac_names,
			GROUP_CONCAT(b.bacteria_abrev) as bac_abrev
			from ci_test_panels te
			join ci_test_groups g ON g.tg_ID = te.te_group_id
			join ci_test_meta tm on tm.test_id = te.te_ID and tm.meta_key = 'bac_tests' and tm.meta_value !='' and tm.meta_value is not null
			JOIN ci_abx_bacterias b ON FIND_IN_SET(b.ID, tm.meta_value) > 0
			where tg_antibiotic = 0  and tg_name not like '%Antibiotic%' 
			and te.te_is_control = 0
			and te.te_in_req = 1
			and ( tg_parent = {$mgid} or tg_ID = {$mgid} )
			group by te.te_ID
			";

			if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
				$cache = new Tr_Cache_Query('reportabx');
				$bacteria_tests = $cache->get_results($sql_bacteria, OBJECT, $cache_query_seconds);
			} else {
				$bacteria_tests = $wpdb->get_results($sql_bacteria, OBJECT);
			}

			foreach ($bacteria_tests as $bt) {
				$testkey = strtoupper(trim((string)$bt->te_name));
				$color 		= trim((string)$bt->te_color);


				if (!isset($bacterias[$testkey])) {
					$bacterias[$testkey] = array(
						'ids' => array(),
						'keys' 	=> array(),
						'name' 	=> $bt->te_name,
						'abrev' => $bt->te_abrev,
						'color' => $color,
						'gid' 	=> $mgid,
						'bacIDs' => array(),
						'bacNames' => array_unique(array_map('trim', explode(',', $bt->bac_names))),
						'BacAbrevs' => array_unique(array_map('trim', explode(',', $bt->bac_abrev))),
					);
				}

				$bacterias[$testkey]['keys'] = array_filter(array_unique(array_merge(
					$bacterias[$testkey]['keys'],
					array_map('strtoupper', array_filter(array_unique(array($bt->te_name, $bt->te_abrev, $bt->te_abrev2))))
				)));

				$bacterias[$testkey]['bacIDs'] = array_unique(array_merge(
					$bacterias[$testkey]['bacIDs'],
					array_map('intval', explode(',', $bt->bac_IDs))
				));


				$bacterias[$testkey]['ids'][] = $bt->te_ID;
			}
		}
		return $bacterias;
	}

	public static function get_markers_by_groups($group_gids, $bac_ids = array(), $resistant = false)
	{
		global $wpdb, $cache_query_seconds;

		if (empty($group_gids)) {
			return array();
		}
		$markers = array();

		$group_gids_str = implode(' , ', $group_gids);
		$fields = '';
		$joins = '';
		if (!empty($bac_ids)) {
			$bac_ids_str = implode(' , ', $bac_ids);
			$fields .= " GROUP_CONCAT(DISTINCT bb.bacteria_ID) as bacteria_IDs, ";
			$joins .= " JOIN ci_abx_bacteria_markers bb ON bb.marker_ID = m.ID AND bb.bacteria_ID IN ({$bac_ids_str}) ";
		}

		if ($resistant) {
			$fields .= " GROUP_CONCAT(DISTINCT cl.resistants SEPARATOR ',') as resistants, ";
			$joins .= " JOIN ci_abx_rm_class cl ON cl.ID = m.class_ID ";
		}

		$sql = "select 
		{$fields}
		te.te_ID, 
		te.te_name, 
		te.te_abrev,
		te.te_abrev2,
		GROUP_CONCAT(m.ID) as bac_IDs,
		GROUP_CONCAT(m.resistance_marker) as markers,
		GROUP_CONCAT(m.abrev) as marker_abrevs
        from ci_test_panels te
		join ci_test_meta tm on tm.test_id = te.te_ID and tm.meta_key = 'mkr_tests' and tm.meta_value !='' and tm.meta_value is not null
		JOIN ci_abx_resistance_markers m ON FIND_IN_SET(m.ID, tm.meta_value) > 0
        JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
		{$joins}
        where (g.tg_antibiotic = 1 or g.tg_name like '%Antibiotic Resistance%')
        and (tg_parent in ({$group_gids_str}) or tg_ID in ({$group_gids_str}) ) 
		GROUP by te.te_ID
		";

		if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
			$cache = new Tr_Cache_Query('reportabx');
			$marker_tests = $cache->get_results($sql, OBJECT, $cache_query_seconds);
		} else {
			$marker_tests = $wpdb->get_results($sql, OBJECT);
		}

		//echo $wpdb->last_error;

		foreach ($marker_tests as $mt) {
			$testkey = strtoupper(trim((string)$mt->te_name));
			$keys = array_filter(array_unique(array(
				strtoupper($mt->te_name),
				strtoupper($mt->te_abrev),
				strtoupper($mt->te_abrev2),
			)));
			$row = array(
				'ids' => array(),
				'name'  => $mt->te_name,
				'abrev' => $mt->te_abrev,
				'keys'  => $keys,
				'markerIDs' => array_unique(array_map('intval', explode(',', $mt->bac_IDs))),
				'markers' => array_unique(array_map('trim', explode(',', $mt->markers))),
				'markerAbrevs' => array_unique(array_map('trim', explode(',', $mt->marker_abrevs))),

			);

			if ($resistant) {
				if (empty($mt->resistants)) {
					continue;
				}
				$row['resistants'] = array_unique(array_map('trim', explode(',', $mt->resistants)));
				$row['bacteria_IDs'] = array_unique(array_map('intval', explode(',', $mt->bacteria_IDs)));
			}
			if (!isset($markers[$testkey])) {
				$markers[$testkey] = $row;
			}

			$markers[$testkey]['ids'][] = $mt->te_ID;
		}
		return $markers;
	}


	public static function get_panel_id_by_type($type)
	{
		global $wpdb;

		$ids = $wpdb->get_col($wpdb->prepare("select tg_ID 
		from ci_test_groups 
		where tg_type='pcr' and tg_parent = 0
		and tg_other_options like %s", '%abx_type":"' . $wpdb->esc_like($type) . '"%'));
		return array_map('intval', $ids);
	}


	public static function get_genotypic_antibiogram_summary($where, $main_abx_group_gids, $wherebygroups, $date_from, $date_to, $tab)
	{
		global $wpdb;
		global $cache_query_seconds;
		//$cache_query_seconds = 0;

		$show_debug_link = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'genotypic') !== false;

		$debug_data = array(
			'columns' => array(),
			'rows' => array(),
		);
		$return = array(
			'date_sel' => '',
			'columns' => array(),
			'rows' => array(),
		);

		if (empty($main_abx_group_gids)) {
			return $return;
		}

		$bacterias = self::get_bacterias_by_groups($main_abx_group_gids);
		$bac_ids = array();
		foreach ($bacterias as $b) {
			$bac_ids = array_merge($bac_ids, $b['bacIDs']);
		}

		$markers 	= self::get_markers_by_groups($main_abx_group_gids, $bac_ids, true);

		$bacterias = array_filter($bacterias, function ($b) use ($markers) {
			foreach ($markers as $m) {
				if (!empty(array_intersect($b['bacIDs'], $m['bacteria_IDs']))) {
					return true;
				}
			}
			return false;
		});


		$resistant_maps = array();
		foreach ($markers as $tid => $m) {
			foreach ($m['resistants'] as $resistant) {
				if (empty($resistant)) continue;
				if (!in_array($resistant, $return['columns'], true)) {
					$return['columns'][] = $resistant;
				}
				foreach ($bacterias as $bid => $b) {
					if (!empty(array_intersect($b['bacIDs'], $m['bacteria_IDs']))) {
						if (!isset($resistant_maps[$bid])) {
							$resistant_maps[$bid] = array();
						}
						$resistant_maps[$bid][$resistant] = array_unique(array_merge($resistant_maps[$bid][$resistant] ?? array(), $m['keys']));
					}
				}
			}
		}





		$fieldpositive = 're.has_uti > 0';
		if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
			$fieldpositive .= ' or re.has_pos > 0';
		}


		$sql_samples = "
			select
				ba.mid,
				ba.assen,
				re.id as report_id,
				GROUP_CONCAT(DISTINCT UPPER(rs.drug) ORDER BY rs.drug SEPARATOR ',') as drug_list
			from ci_results_pcr rs
			join ci_reports_pcr re on re.id = rs.report_id
			join ci_batches_pcr ba on ba.assen = re.patient_id
			where {$where}
				and re.estatus = 'C'
				and " . trpdd_abx_query_date_range($date_from, $date_to) . "
				and rs.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL')
				and rs.has_order = 1
				and ({$wherebygroups})
				and ({$fieldpositive})
			group by ba.mid
		";

		/*
		$sql_samples = "
			select * from {$abx_table} r
			JOIN ci_batches_pcr ba on ba.mid = r.mid
			where {$where}
		";
		
		if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
			$cache = new Tr_Cache_Query('reportabx');
			$sample_rows = $cache->get_results($sql_samples, ARRAY_A, $cache_query_seconds);
		} else {
			$sample_rows = $wpdb->get_results($sql_samples, ARRAY_A);
		}
		$count_samples = array();

		foreach ($sample_rows as $row) {

			foreach ($bacterias as $tid => $b) {
				if (isset($row["b_{$tid}"]) && $row["b_{$tid}"] == 1) {
					$count_samples[$tid]++;
					foreach ($markers as $tmkid => $m) {
						if (isset($row["m_{$tmkid}"]) && $row["m_{$tmkid}"] == 1) {
							$count_samples[$tid . '_m_' . $tmkid]++;
						}
					}
				}
			}
		}
			*/

		if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
			$cache = new Tr_Cache_Query('reportabx');
			$sample_rows = $cache->get_results($sql_samples, ARRAY_A, $cache_query_seconds);
		} else {
			$sample_rows = $wpdb->get_results($sql_samples, ARRAY_A);
		}
		$count_samples = array();
		$columns_count = array();


		foreach ($sample_rows as $row) {

			$drug_tokens = array_filter(array_map('trim', explode(',', strtoupper($row['drug_list']))));
			if (empty($drug_tokens)) {
				continue;
			}

			foreach ($bacterias as $tid => $b) {

				if (!empty(array_intersect($b['keys'], $drug_tokens))) {

					if (isset($_GET['debugabx']) && $_GET['b'] == $tid) {
						if (!isset($debug_data['rows'][$row['assen']])) {
							$debug_data['rows'][$row['assen']] = array(
								'accession' => $row['assen'],
								'values' => array(),
							);
						}
						if (!isset($debug_data['columns'][$b['name']])) {
							$debug_data['columns'][$b['name']] = $b['name'];
						}
						$debug_data['rows'][$row['assen']]['values'][$b['name']] = 1;
					}


					if (!isset($count_samples[$tid])) {
						$count_samples[$tid] = array(
							'count' => 0,
							'resistant_counts' => array(),
						);
					}
					$count_samples[$tid]['count']++;

					if (isset($resistant_maps[$tid])) {
						foreach ($resistant_maps[$tid] as $resistant => $markerkeys) {
							if (!empty(array_intersect($markerkeys, $drug_tokens))) {
								$columns_count[$resistant] = ($columns_count[$resistant] ?? 0) + 1;
								$count_samples[$tid]['resistant_counts'][$resistant] = ($count_samples[$tid]['resistant_counts'][$resistant] ?? 0) + 1;
							}
						}

						if (isset($_GET['debugabx']) && $_GET['b'] == $tid) {

							foreach ($markers as $tid => $m) {
								foreach ($m['resistants'] as $res1) {
									if ($res1 == $_GET['r']) {
										if (!isset($debug_data['columns'][$m['name']])) {
											$debug_data['columns'][$m['name']] = $m['name'];
										}
										if (!empty(array_intersect($m['keys'], $drug_tokens))) {
											$debug_data['rows'][$row['assen']]['values'][$m['name']] = 1;
										}
									}
								}
							}
						}
					}
				}
			}
		}

		foreach ($bacterias as $tid => $b) {

			$countarr = $count_samples[$tid] ?? array('count' => 0, 'resistant_counts' => array());
			$b_count = $count_samples[$tid]['count'] ?? 0;
			if ($b_count > 0) {
				$row = array(
					'id' => $tid,
					'name' => $b['abrev'] ?? $b['name'],
					'full_name' => $b['name'],
					'samples' => $b_count,
					'values' => array(),
				);

				foreach ($countarr['resistant_counts'] as $resistant => $resistant_count) {
					$row['values'][$resistant] = array(
						'applicable' => true,
						'resistant' => $b_count > 0 ? round(($resistant_count / $b_count) * 100, 1) : 0,
					);
				}

				if ($show_debug_link) {
					foreach ($resistant_maps[$tid] ?? array() as $resistant => $markerkeys) {
						if (!isset($row['values'][$resistant])) {
							$row['values'][$resistant] = array(
								'applicable' => true,
								'resistant' => '-',
							);
						}
						$link = add_query_arg($_POST, Tr_Base_Class_V6::link(array('debugabx' => '1', 'sub_ajax' => 'genotypic_antibiogram', 'b' => $tid, 'r' => $resistant), false));
						$row['values'][$resistant]['link'] = $link;
					}
				}

				$return['rows'][] = $row;
			}
		}

		//remove columns with no data
		$return['columns'] = array_filter($return['columns'], function ($col) use ($columns_count) {
			return isset($columns_count[$col]) && $columns_count[$col] > 0;
		});
		$return['columns'] = array_values($return['columns']);

		usort($return['rows'], function ($a, $b) {
			if ($a['samples'] === $b['samples']) {
				return strcasecmp($a['name'], $b['name']);
			}
			return $b['samples'] <=> $a['samples'];
		});


		if (isset($_GET['debugabx'])) {
			self::show_debug_table($debug_data);
			echo '<pre>';
			var_dump($resistant_maps[$_GET['b']] ?? array());
			echo '</pre>';
			exit;
		}

		return $return;
	}

	public static function show_debug_table($data)
	{
		echo '<table border="1" cellpadding="5" class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">';
		echo '<tr><th>Accession</th>';
		foreach ($data['columns'] as $col) {
			echo '<th>' . esc_html($col) . '</th>';
		}
		echo '</tr>';

		foreach ($data['rows'] as $row) {
			echo '<tr>';
			echo '<td>' . esc_html($row['accession']) . '</td>';
			foreach ($data['columns'] as $col) {
				echo '<td>' . (isset($row['values'][$col]) ? 'X' : '') . '</td>';
			}
			echo '</tr>';
		}

		echo '</table>';
		echo '<style>.report_tbl th, .report_tbl td { border: 1px solid #000; }</style>';
	}

	public static function get_critical_findings_categories($main_abx_group_gids)
	{
		global $wpdb;

		$category_cf = [
			'MRSA' => [
				'Organism' => ['Staphylococcus aureus'],
				'Marker' => ['mecA', 'mecC']
			],
			'VRE' => [
				'Organism' => ['Enterococcus faecalis', 'E. faecium', 'Enterococcus Spp'],
				'Marker' => ['vanA', 'vanB', 'vanC', 'vanM']
			],
			'ESBL' => [
				'Organism' => ['Escherichia coli', 'Klebsiella pneumoniae', 'Klebsiella oxytoca', 'Klebsiella aerogenes', 'Enterobacter cloacae', 'Citrobacter freundii', 'Citrobacter koseri', 'Serratia marcescens', 'Proteus mirabilis', 'Proteus vulgaris', 'Morganella morganii', 'Providencia stuartii'],
				'Marker' => ['CTX-M', 'VEB']
			],
			'CRE' => [
				'Organism' => ['Escherichia coli', 'Klebsiella pneumoniae', 'Klebsiella oxytoca', 'Klebsiella aerogenes', 'Enterobacter cloacae', 'Citrobacter freundii', 'Citrobacter koseri', 'Serratia marcescens', 'Proteus mirabilis', 'Proteus vulgaris', 'Morganella morganii', 'Providencia stuartii'],
				'Marker' => ['KPC', 'NDM', 'VIM', 'IMP', 'OXA-48', 'GES']
			],
			'MDR_PA' => [
				'Organism' => ['Pseudomonas aeruginosa'],
				'Marker' => ['KPC', 'NDM', 'VIM', 'IMP', 'GES']
			],
			'MDR_AB' => [
				'Organism' => ['Acinetobacter baumannii complex'],
				'Marker' => ['OXA-23', 'OXA-51', 'OXA-72', 'NDM', 'IMP', 'VIM']
			],
		];
		$all_bacs = array();
		$all_mkrs = array();
		$sql_bacs = "select ID, bacteria_name from ci_abx_bacterias";

		if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
			$cache = new Tr_Cache_Query('reportabx');
			$bacrs = $cache->get_results($sql_bacs, OBJECT, 3600);
		} else {
			$bacrs = $wpdb->get_results($sql_bacs, OBJECT);
		}

		foreach ($bacrs as $b) {
			$all_bacs[$b->ID] = $b->bacteria_name;
		}

		$sql_mkrs = "select ID, resistance_marker,abrev from ci_abx_resistance_markers";
		if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
			$cache = new Tr_Cache_Query('reportabx');
			$mkrrs = $cache->get_results($sql_mkrs, OBJECT, 3600);
		} else {
			$mkrrs = $wpdb->get_results($sql_mkrs, OBJECT);
		}
		foreach ($mkrrs as $m) {
			$all_mkrs[$m->ID] = $m->resistance_marker;
		}



		//fix tests
		$main_abx_group_gids_str = implode(',', $main_abx_group_gids);
		$sql_tests = "select 
		te_name, 
		te_abrev, 
		te_abrev2,
		bm.meta_value as bac_tests,
		mm.meta_value as mkr_tests
		from ci_test_panels te
		left join ci_test_meta bm on bm.test_id = te.te_ID and bm.meta_key = 'bac_tests'
		left join ci_test_meta mm on mm.test_id = te.te_ID and mm.meta_key = 'mkr_tests'
		JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
		where (tg_parent in ({$main_abx_group_gids_str}) or tg_ID in ({$main_abx_group_gids_str}))
		and te.te_is_control = 0 
		and te.te_in_req =1 
		and te_type = 'pcr'";


		if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
			$cache = new Tr_Cache_Query('reportabx');
			$alltestrs = $cache->get_results($sql_tests, OBJECT, 3600);
		} else {
			$alltestrs = $wpdb->get_results($sql_tests, OBJECT);
		}




		$alltests = array();
		foreach ($alltestrs as $t) {
			$t->te_abrev = strtoupper($t->te_abrev);
			$t->te_name = strtoupper($t->te_name);

			if (!empty($t->te_abrev)) {
				if (!isset($alltests[$t->te_name])) {
					$alltests[$t->te_name] = array();
				}
				if (!isset($alltests[$t->te_abrev])) {
					$alltests[$t->te_abrev] = array();
				}

				$alltests[$t->te_name][] = $t->te_abrev;
				$alltests[$t->te_abrev][] = $t->te_name;
			}

			if (!empty($t->te_abrev2)) {
				$t->te_abrev2 = strtoupper($t->te_abrev2);
				if (!isset($alltests[$t->te_abrev2])) {
					$alltests[$t->te_abrev2] = array();
				}
				$alltests[$t->te_abrev2][] = $t->te_name;
				$alltests[$t->te_name][] = $t->te_abrev2;
			}
			$bacids = !empty($t->bac_tests) ? explode(',', $t->bac_tests) : array();
			foreach ($bacids as $bid) {
				$bacname = isset($all_bacs[$bid]) ? strtoupper($all_bacs[$bid]) : '';
				if (!empty($bacname)) {
					if (!isset($alltests[$bacname])) {
						$alltests[$bacname] = array();
					}
					$alltests[$bacname][] = $t->te_name;
					if (!empty($t->te_abrev)) {
						$alltests[$bacname][] = $t->te_abrev;
					}
				}
			}
			$mkrids = !empty($t->mkr_tests) ? explode(',', $t->mkr_tests) : array();
			foreach ($mkrids as $mid) {
				$mkrname = isset($all_mkrs[$mid]) ? strtoupper($all_mkrs[$mid]) : '';
				if (!empty($mkrname)) {
					if (!isset($alltests[$mkrname])) {
						$alltests[$mkrname] = array();
					}
					$alltests[$mkrname][] = $t->te_name;
					if (!empty($t->te_abrev)) {
						$alltests[$mkrname][] = $t->te_abrev;
					}
				}
			}
		}

		foreach ($category_cf as $cf_name => $cf_data) {
			$cf_data['Organism']  = array_map('strtoupper', $cf_data['Organism']);
			$cf_data['Marker']    = array_map('strtoupper', $cf_data['Marker']);

			foreach ($cf_data['Organism'] as $drug) {
				if (isset($alltests[$drug])) {
					foreach ($alltests[$drug] as $dt) {
						if (!in_array($dt, $category_cf[$cf_name]['Organism'])) {
							$category_cf[$cf_name]['Organism'][] = $dt;
						}
					}
				}
			}

			foreach ($cf_data['Marker'] as $drug) {
				if (isset($alltests[$drug])) {
					foreach ($alltests[$drug] as $dt) {
						if (!in_array($dt, $category_cf[$cf_name]['Marker'])) {
							$category_cf[$cf_name]['Marker'][] = $dt;
						}
					}
				}
			}
		}

		return $category_cf;
	}

	public static function count_critical_markers($user_conditions, $category_cf, $wherebygroups, $date_from, $date_to)
	{
		global $wpdb;
		global $cache_query_seconds;

		$result = [];
		if (empty($wherebygroups)) {
			return $result;
		}

		$fieldpositive = 're.has_uti > 0';
		if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
			$fieldpositive .= ' or re.has_pos > 0';
		}


		$alldrugs = [];

		$subselects = array();
		foreach ($category_cf as $cf_name => $cf_data) {

			$alldrugs = array_unique(array_merge(
				$alldrugs,
				$cf_data['Organism'],
				$cf_data['Marker']
			));
			$where_Oragans = array();
			foreach ($cf_data['Organism'] as $org) {
				$where_Oragans[] = "find_in_set('{$org}', drug_list)";
			}
			$where_Markers = array();
			foreach ($cf_data['Marker'] as $marker) {
				$where_Markers[] = "find_in_set('{$marker}', drug_list)";
			}
			//number each type
			$subselects[] = "SUM(IF( 
                            (" . implode(' OR ', $where_Oragans) . " ) AND ( " . implode(' OR ', $where_Markers) . ")
                            ,1,0)) AS {$cf_name} ";

			//list accession each type
			$subselects[] = "GROUP_CONCAT(IF( 
                            (" . implode(' OR ', $where_Oragans) . " ) AND ( " . implode(' OR ', $where_Markers) . ")
                            ,assen,'')) AS {$cf_name}_assens ";
		}
		$alldrugs_str = implode("','", array_map('esc_sql', $alldrugs));

		$subselects_str = implode(", ", $subselects);

		$where       = ' ba.is_saved = 0 ' . $user_conditions['where'];

		$sql = "
        SELECT 
        cli_ID,
        {$subselects_str}
        FROM  ( SELECT ba.cli_ID, 
			ba.assen,
            GROUP_CONCAT(DISTINCT rs.drug ORDER BY rs.drug) as drug_list
            FROM ci_results_pcr rs
            JOIN ci_reports_pcr re ON re.id = rs.report_id
            JOIN ci_batches_pcr ba ON ba.assen = re.patient_id
            WHERE {$where}
			  AND re.estatus = 'C'
              AND " . trpdd_abx_query_date_range($date_from, $date_to) . "  
              AND rs.drug IN ('{$alldrugs_str}')
              AND rs.`pos-neg` IN ('POS','POH','POL','POM','PVH','PVL')
              AND ({$wherebygroups})
              AND ({$fieldpositive})
            GROUP BY ba.cli_ID, re.id ) as rs1
        GROUP BY cli_ID  
        ";


		if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

			$cache = new Tr_Cache_Query('reportabx');
			$rows = $cache->get_results($sql, ARRAY_A, $cache_query_seconds);
		} else {
			$rows = $wpdb->get_results($sql, ARRAY_A);
		}


		//file_put_contents(WP_CONTENT_DIR . '/debug_sql.txt',  $sql . "\n\n");

		foreach ($rows as $row) {
			$result[$row['cli_ID']] = $row;
		}

		return $result;
	}
}
