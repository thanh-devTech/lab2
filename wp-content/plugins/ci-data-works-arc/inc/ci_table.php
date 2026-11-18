<?php
class CI_Table
{

    public $config;
    public $version     = '1.2';
    public $url         = '';
    public $bk_folder   = '';
    public $bk_url      = '';
    public $filteredbys = array();

    public function __construct($cf)
    {

        if (isset($cf['backup']) && $cf['backup']) {
            $cf['actions']['tb_backups'] = 'Backups';
        }
        if (isset($_GET['filters']) && is_array($_GET['filters'])) {
            foreach ($_GET['filters'] as $k => $vl) {
                if (!empty($vl)) {
                    $this->filteredbys[$k] = $vl;
                }
            }
        }
        $this->config = $cf;
        $this->config['id'] = time();
        $this->bk_folder = WP_CONTENT_DIR . '/backups/' . $this->config['table'];
        $this->bk_url = '/wp-content/backups/' . $this->config['table'];
        $this->url = Tr_Base_Class_V6::link(array('action' => ''), false);
        $this->checkcolumns();


        add_thickbox();

        $act =  isset($_REQUEST['act']) && !empty($_REQUEST['act']) ? $_REQUEST['act'] : '';

        if (empty($act) && isset($_REQUEST['trajax'])) {
            $act = $_REQUEST['trajax'];
        }
        if (!empty($act)) {
            if ($act == 'save_table') {

                if ($this->config['backup']) {
                    $this->backup();
                }
                if (isset($this->config['save_func'])) {
                    call_user_func($this->config['save_func'], $this);
                } else {
                    $this->save_table();
                }
            } else if ($act == 'remove_table_row') {
                if ($this->config['backup']) {
                    $this->backup();
                }
                $this->remove_table_row();
            } else if ($act == 'export' || strpos($act, 'export_key_') !== false) {
                $key = $act == 'export' ? '' : str_replace('export_key_', '', $act);
                $this->export($key);
            } else if ($act == 'import_frame' || strpos($act, 'import_frame_key_') !== false) {
                $key = $act == 'import_frame' ? '' : str_replace('import_frame_key_', '', $act);
                if ($this->config['backup']) {
                    $this->backup();
                }
                $this->import_frame($key);
            } else if ($act == 'tb_backups') {
                $this->show_backups();
            } else if ($act == 'restore') {
                $this->restore();
            } else if ($act == 'remove_bk_file') {
                $this->remove_bk_file();
            } else if ($act == 'backup') {
                $this->backup();
                $this->show_backups();
            } else if ($act == 'linkact') {
                $link = $_REQUEST['link'];
                if (isset($this->config['linkact_' . $link])) {
                    call_user_func($this->config['linkact_' . $link]);
                }
            }
        }
    }

    public function dataRow($row)
    {
        $this->config['data'][] = $row;
    }

    public function setData($data)
    {
        $this->config['data'] = $data;
    }


    public function checkcolumns()
    {
        foreach ($this->config['columns'] as $k => $arr) {
            if (in_array($arr['type'], array('select', 'selectm', 'selectm2', 'select2', 'select1')) && isset($arr['options'][0]) && is_object($arr['options'][0])) {
                $new_opts = array();
                if ($arr['type'] == 'select2') {
                    $new_opts[''] = 'Select';
                }
                foreach ($arr['options'] as $obj) {
                    $new_opts[$obj->ID] = $obj->name;
                }

                $this->config['columns'][$k]['options'] = $new_opts;
            }

            if (isset($arr['options_by']) && $arr['options_by']) {

                $this->config['columns'][$k]['optionkey'] = array_key_first($this->config['columns'][$arr['options_by']]['options']);
                $this->config['columns'][$arr['options_by']]['class'] .= ' ctrloptions ';
            }
        }
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function get_postdata()
    {
        $data = is_array($_REQUEST['data']) ? $_REQUEST['data'] : json_decode(stripslashes($_REQUEST['data']), true);

        if (!$this->config['save_all']) {
            $id     = $_REQUEST['data']['ID'] ? $_REQUEST['data']['ID'] : 0;
            $data   = array($id => $_REQUEST['data']);
        }
        return $data;
    }

    public function backup($meta = array())
    {
        global $wpdb;
        global $lab_backup_file;
        if (!file_exists($this->bk_folder)) {
            wp_mkdir_p($this->bk_folder);
        }
        $filename = date_i18n('Y-m-d_H_i_s') . '.csv';
        $filepath = $this->bk_folder . '/' . $filename;

        $rows   = $wpdb->get_results("select * from {$this->config['table']}", ARRAY_A);
        $f      = fopen($filepath, 'w');
        $lab_backup_file = $filepath;
        $header = array();

        foreach ($rows[0] as $k => $vl) {
            $header[] = $k;
        }
        fputcsv($f, $header);

        foreach ($rows as $row) {
            fputcsv($f, $row);
        }

        if (isset($this->config['meta_dir']) && is_dir($this->config['meta_dir'])) {
            $meta['files'] = array();
            foreach (scandir($this->config['meta_dir']) as $file) {
                if (in_array($file, array('.', '..')) || stripos($file, 'csv.log') > 0 || stripos($file, '.deleted') > 0) continue;
                if (isset($this->config['current_file']) && $this->config['current_file'] == $file) {
                    continue;
                }

                $meta['files'][filemtime($this->config['meta_dir'] . $file)] = $file;
            }

            ksort($meta['files']);
        }

        if (!empty($meta)) {
            file_put_contents($filepath . '.meta', json_encode($meta));
        }

        return $filepath;
    }

    public function export($mckey = '', $outfile = '')
    {
        if (!isset($this->config['data'])) {
            $this->loaddata();
        }
        if (!empty($outfile)) {
            $f = fopen($outfile, 'w');
        } else {
            $filename = $this->config['name'] . (!empty($mckey) ? '-' . $mckey : '') . '.csv';
            $filename = str_replace(' ', '_', $filename);

            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename=' . $filename);
            header('Pragma: no-cache');
            $f = fopen('php://output', 'w');
        }


        if (isset($this->config['row_header']) && count($this->config['row_header']) > 0) {
            $header = array();
            foreach ($this->config['row_header'] as $arr) {
                $arr['title'] = str_replace('<br>', "\n", $arr['title']);
                $arr['title'] = strip_tags($arr['title']);
                $header[] = $arr['title'];
                if ($arr['colspan'] > 1) {
                    for ($i2 = 1; $i2 < $arr['colspan']; $i2++) {
                        $header[] = $arr['title'];
                    }
                }
            }
            fputcsv($f, $header);
        }

        $header = array();
        foreach ($this->config['columns'] as $arr) {
            $arr['label'] = str_replace('<br>', "\n", $arr['label']);
            $arr['label'] = strip_tags($arr['label']);
            $header[] = $arr['label'];
        }
        fputcsv($f, $header);

        foreach ($this->config['data'] as $i => $row) {
            $rowdata = array();
            foreach ($this->config['columns'] as $ckey => $arr) {
                if (!isset($row[$ckey])) {
                    $row[$ckey] = '';
                }
                if (isset($arr['fields']) && count($arr['fields']) > 0 && !empty($mckey)) {
                    $ckey = $ckey . '-' . $mckey;
                }
                if ($arr['type'] == 'desc') {
                    $row[$ckey] = str_replace('<br>', "\n", $row[$ckey]);
                    $row[$ckey] = strip_tags($row[$ckey]);
                } else if ($arr['type'] == 'select2') {
                    $row[$ckey] = isset($arr['options'][$row[$ckey]]) ? $arr['options'][$row[$ckey]] : '';
                }

                if (isset($this->config['exportyesnow']) && $this->config['exportyesnow']) {
                    if ($row[$ckey] == '1') $row[$ckey] = 'YES';
                    else if ($row[$ckey] == '0') $row[$ckey] = 'NO';
                    else if ($row[$ckey] == 'hide_field') {
                        $row[$ckey] = 'N/A';
                    }
                } else if ($row[$ckey] == 'hide_field') {
                    $row[$ckey] = '';
                }
                $rowdata[$ckey] = $row[$ckey];
            }
            fputcsv($f, $rowdata);
        }
        if (empty($outfile)) {
            exit;
        }
    }

    public function import_frame($mckey = '')
    {
        global $wpdb;

        $map2tbl = isset($this->config['map2tables']) && $this->config['map2tables'];

        if (isset($_FILES['csv_file'])) {
            $tab = ",";

            $f = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $datafile           = fgets($f);
            fclose($f);

            $count_semicolon    = substr_count($datafile, ';');
            $count_comma        = substr_count($datafile, ',');
            $datafile           = '';
            if ($count_semicolon > $count_comma) {
                $tab = ';';
            }
            $f = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($f) {
                if (isset($this->config['import_func']) && !empty($this->config['import_func'])) {
                    return call_user_func($this->config['import_func'], $_FILES['csv_file']);
                }
                $cache_data = array();
                foreach ($this->config['columns'] as $ckey => $carr) {

                    if ($carr['type'] == 'select2') {
                        $this->config['columns'][$ckey]['options_low'] = array_map('strtolower', $carr['options']);
                    }
                    if ($carr['type' == 'desc'] &&  !isset($carr['map'])) {
                        continue;
                    }
                    $ckey = str_replace('colid_', '', $ckey);
                    if (isset($carr['map'])) {
                        $cache_data[$ckey] = array();
                        $table      = $carr['map'][0];
                        $namekey    = $carr['map'][1];
                        $ikey       = $carr['map'][2];
                        $sql_selectmap = "select {$namekey}, {$ikey} from {$table}";
                        if (isset($carr['sqlmap'])) {
                            $sql_selectmap = $carr['sqlmap'];
                        }
                        $rs         = $wpdb->get_results($sql_selectmap);

                        foreach ($rs as $r) {
                            $cache_data[$ckey][$r->{$namekey}] = $r->{$ikey};
                        }
                    }
                }



                $header = array();
                $check_exist = array();
                $insert_all = false;
                if ($this->config['hasfilter'] && isset($this->config['save_map'])) {

                    foreach ($this->config['save_map']['key'] as $fk => $pos) {
                        if ($fk != 'field') {
                            if (!in_array($fk, $check_exist)) {
                                $check_exist[] = $fk;
                            }
                        }
                    }
                }

                if (isset($_REQUEST['cleardata']) && $_REQUEST['cleardata']) {

                    $wpdb->query("truncate table {$this->config['table']}");
                    $insert_all = true;
                }


                while ($row = fgetcsv($f, null, $tab)) {
                    if (empty($header)) {

                        //check header
                        if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
                            continue;
                        } else {
                            $header = array();
                            foreach ($row as $i => $tt) {
                                $header[strtolower($tt)] = $i;
                            }

                            //check row header
                            if (isset($this->config['row_header']) && count($this->config['row_header']) > 0) {
                                if ($row[1] == $this->config['row_header'][1]['title']) {
                                    $header = array();
                                }
                            }
                        }

                        continue;
                    } else {
                        $rows   = array();
                        $rowdata = array();
                        $multirows = array();

                        $inum = 0;
                        $empty_row = false;
                        $map1key = '';
                        $map1value = '';

                        if ($map2tbl && empty($row[0])) continue;

                        if ($map2tbl) {
                            $map1value = $row[0];
                            $map1key = $this->config['columns'][0]['column'];
                            if (isset($this->config['columns'][0]['map'])) {
                                $map1value = $cache_data[0][$map1value];
                            }
                        }


                        foreach ($this->config['columns'] as $ckey => $arr) {

                            if ($ckey == '1' && $arr['type'] == 'desc') {
                                continue;
                            } else if ($map2tbl && $ckey == '0') {
                                continue;
                            }

                            $ckey = str_replace('colid_', '', $ckey);

                            $colkey = strtolower($arr['label']);
                            $vl     = $row[$header[$colkey]];

                            if ($arr['type'] == 'select2') {
                                $vllow = strtolower(trim($vl));
                                $vl = array_search($vllow, $arr['options_low']);
                            }


                            if (isset($arr['column'])) {
                                if (!isset($multirows[$arr['column']])) {
                                    $multirows[$arr['column']] = array();
                                }

                                if (!empty($vl)) {
                                    $colvl = $arr['label'];
                                    if (isset($arr['map'])) {
                                        $colvl = $cache_data[$ckey][$colvl];
                                    }



                                    if ($map2tbl) {
                                        if (stripos($vl, 'yes') !== false || $vl == 1) {
                                            $multirows[$arr['column']][$colvl] = array($map1key, $map1value);
                                        }
                                    } else {
                                        $multirows[$arr['column']][$colvl] = array($arr['mapto'], $vl);
                                    }
                                }
                            } else if (isset($arr['map'])) {


                                if (!isset($cache_data[$ckey][$vl])) {
                                    $empty_row = true;
                                    continue;
                                }
                                $rowdata[$ckey] = $cache_data[$ckey][$vl];
                            } else {
                                $rowdata[$ckey] = $vl;
                            }

                            $inum++;
                        }




                        if ($empty_row) {
                            continue;
                        }


                        if (count($multirows) > 0) {

                            foreach ($multirows as $ck => $arr1) {
                                foreach ($arr1 as $ltxt => $arr2) {
                                    $tmprow = $rowdata;
                                    $tmprow[$ck] = $ltxt;
                                    $tmprow[$arr2[0]] = $arr2[1];

                                    $rows[] = $tmprow;
                                }
                            }
                        } else {
                            $rows[] = $rowdata;
                        }


                        foreach ($rows as $rowdata) {

                            $updateby = array();
                            if (!$insert_all && count($check_exist) > 0) {
                                //check exist
                                if (count($check_exist) > 0) {
                                    $where_exist = '';

                                    foreach ($check_exist as $ckey) {
                                        $where_exist .= " and {$ckey} = '{$rowdata[$ckey]}' ";
                                        $updateby[$ckey] = $rowdata[$ckey];
                                    }

                                    $sql_exist = "select count(*) from {$this->config['table']} where 1 {$where_exist}";
                                    $exist = $wpdb->get_var($sql_exist);

                                    if (!$exist) {
                                        $id = 0;
                                        $updateby = array();
                                    }
                                }
                            }

                            if (count($updateby) > 0) {
                                $wpdb->update($this->config['table'], $rowdata, $updateby);
                            } else {
                                $wpdb->insert($this->config['table'], $rowdata);
                            }
                        }
                    }
                }

                if (isset($this->config['saved_change']) && !empty($this->config['saved_change'])) {
                    try {
                        call_user_func($this->config['saved_change']);
                    } catch (Exception $e) {
                    }
                }


?>
                <h3 style="text-align:center">Imported Successfully!</h3>

                <script>
                    window.parent.location.reload();
                </script>

        <?php
                exit;
            }
        }
        ?>
        <form method="post" enctype="multipart/form-data">
            <div style="margin:50px;text-align:center">
                <input type="file" name="csv_file" />
                <br>
                <br>
                <label>
                    <input type="checkbox" name="cleardata" value="1" /> Clear data before import
                </label>
                <br><br>
                <input type="submit" class="button" value="Import" onclick="this.value='Importing...'" />
                <input type="hidden" name="act" value="import_frame" />
            </div>
        </form>

    <?php
        exit;
    }

    public function save_table()
    {
        global $wpdb;

        $columns    = $wpdb->get_col("explain {$this->config['table']}");


        $return     = array('status' => 'ok', 'ID' => 0, 'IDs' => array());
        $rows       = array();
        $data       = $this->get_postdata();
        $check_exist = array();
        $column_by_map = false;
        $update_fields = array();
        $insert_all = false;
        $has_filter = isset($this->config['hasfilter']) && $this->config['hasfilter'];
        if (is_array($this->filteredbys) && count($this->filteredbys) > 0) {
            $has_filter = true;
        }
        $has_limit  = isset($this->config['limit']) && $this->config['limit'] > 0;

        if ($this->config['save_all'] && isset($this->config['save_map'])) {

            $save_map = $this->config['save_map'];
            $column_by_map = true;

            if (!$has_filter && !$has_limit) {
                $insert_all = true;
                $wpdb->query("truncate table {$this->config['table']}");
            } else {
                foreach ($save_map['key'] as $fk => $pos) {
                    if ($fk != 'field') {
                        if (!in_array($fk, $check_exist)) {
                            $check_exist[] = $fk;
                        }
                    }
                }
            }




            foreach ($data as $k => $vl) {

                $row = array();
                $arr = explode('-', $k, count($save_map['key']));

                foreach ($save_map['key'] as $fk => $pos) {
                    $svl = str_replace(array('colid_', 'ant_'), '', $arr[$pos]);
                    if ($fk == 'field') {
                        $save_map['value'] = $svl;
                        $k = str_replace('-' . $svl, '', $k);
                    } else {
                        $row[$fk] = $svl;
                    }
                }

                if (isset($save_map['value'])) {
                    if ($insert_all && empty($vl)) {
                        continue;
                    }
                    $row[$save_map['value']] = $vl;

                    if (!$insert_all) {
                        $update_fields[$save_map['value']] = $save_map['value'];
                    }
                }

                if (!isset($rows[$k])) {
                    $rows[$k] = $row;
                } else if (isset($save_map['value'])) {
                    if (!$insert_all) {
                        $rows[$k][$save_map['value']] = $vl;
                        $update_fields[$save_map['value']] = $save_map['value'];
                    }
                }
            }
        } else {
            $rows = $data;
        }

        $replace_columns = array();
        foreach ($this->config['columns'] as $colkey => $carr) {
            if (isset($carr['replaces']) && is_array($carr['replaces'])) {
                $re_search = array();
                $re_replace = array();
                foreach ($carr['replaces'] as $sk => $svl) {
                    $re_search[] = $sk;
                    $re_replace[] = $svl;
                }
                $replace_columns[$colkey] = array(
                    0 => $re_search,
                    1 => $re_replace,
                );
            }
        }


        foreach ($rows as $id => $row) {

            unset($row['ID']);

            foreach ($row as $k => $vl) {
                if (is_string($vl)) {
                    $row[$k] = trim($vl);
                }
            }

            if (isset($this->config['hide_fields']) && count($this->config['hide_fields']) > 0) {
                foreach ($this->config['hide_fields'] as $k => $vl) {
                    $row[$k] = $vl;
                }
            }


            if (isset($this->config['only_columns']) && count($this->config['only_columns']) > 0) {
                foreach ($this->config['only_columns'] as $k => $vl) {
                    $row[$k] = $vl;
                }
            }



            foreach ($replace_columns as $colkey => $replaces) {
                if (is_string($row[$colkey])) {
                    $row[$colkey] = str_replace($replaces[1], $replaces[0], $row[$colkey]);
                }
            }

            if (!$column_by_map) {
                foreach ($this->config['columns'] as $k => $arr) {
                    if (isset($arr['fields']) && count($arr['fields']) > 0) {
                    } else if ($arr['type'] == 'select2m') {
                        if (isset($arr['split']) && !empty($arr['split'])) {
                            if (is_array($row[$k])) {
                                $row[$k] = implode($arr['split'], $row[$k]);
                            }
                        } else {
                            $row[$k] = json_encode(is_array($row[$k]) ? $row[$k] : array());
                        }
                    } else if (isset($row[$k])) {
                        $row[$k] = stripslashes($row[$k]);
                    }
                }
            }

            $updateby = array('ID' => $id);

            if (count($check_exist) > 0) {
                $where_exist = '';
                $updateby = array();
                foreach ($check_exist as $ckey) {
                    $where_exist .= " and {$ckey} = '{$row[$ckey]}' ";
                    $updateby[$ckey] = $row[$ckey];
                }

                $sql_exist = "select count(*) from {$this->config['table']} where 1 {$where_exist}";
                $exist = $wpdb->get_var($sql_exist);

                if (!$exist) {
                    $id = 0;
                    $updateby = array();
                    if (count($update_fields) > 0) {
                        $is_empty_row = true;
                        foreach ($update_fields as $ufk) {
                            if ($row[$ufk] !== '') {
                                $is_empty_row = false;
                            }
                        }
                        if ($is_empty_row) {
                            continue;
                        }
                    }
                } else {
                    // $id = time();
                }
            }

            if (isset($this->config['save_filter']) && !empty($this->config['save_filter'])) {
                $row = call_user_func($this->config['save_filter'], $row);
            }


            if ($insert_all || $id == 0 || stripos($id, 'new') !== false) {

                if (in_array('created', $columns)) {
                    $row['created'] = date_i18n('Y-m-d H:i:s');
                }
                if (in_array('created_by', $columns)) {
                    $row['created_by'] = get_current_user_id();
                }
                $rsiu = $wpdb->insert($this->config['table'], $row);
                $newid = $wpdb->insert_id;
            } else {
                $rsiu = $wpdb->update($this->config['table'], $row, $updateby);
                $newid = $id;
            }
            if ($rsiu === false) {
                $return['error_data'][] = $row;
                $return['errors'][] = $wpdb->last_error;
            }

            $return['ID'] = $newid;
            $return['IDs'][$id] = $newid;
        }

        if (isset($this->config['saved_change']) && !empty($this->config['saved_change'])) {
            try {
                call_user_func($this->config['saved_change']);
            } catch (Exception $e) {
            }
        }

        wp_send_json($return);
    }

    public function remove_table_row()
    {
        global $wpdb;

        $return = array('status' => 'ok', 'ID' => 0);
        $id = $_REQUEST['ID'];
        $wpdb->delete($this->config['table'], array('ID' => $id));

        if (isset($this->config['saved_change']) && !empty($this->config['saved_change'])) {
            try {
                call_user_func($this->config['saved_change']);
            } catch (Exception $e) {
            }
        }

        wp_send_json($return);
    }

    public function loaddata()
    {
        global $wpdb;

        $limitstr   = '';
        $where      = '1';
        if (isset($this->config['only_columns']) && is_array($this->config['only_columns'])) {
            foreach ($this->config['only_columns'] as $c => $cvl) {
                $where .= " and {$c} = '{$cvl}' ";
            }
        }

        if (is_array($this->filteredbys) && count($this->filteredbys) > 0) {
            foreach ($this->filteredbys as $k => $vl) {
                $col = $this->config['columns'][$k];
                if (in_array($col['type'], array('select2m', 'selectm'))) {
                    $where .= " and FIND_IN_SET('{$vl}', {$k}) ";
                } else {
                    $where .= " and {$k} like '%{$vl}%' ";
                }
            }
        }

        if (isset($this->config['limit']) && $this->config['limit'] > 0) {
            $row_per_page  = $this->config['limit'];
            $pagination    = new Tr_Pagination_class2($row_per_page);
            $offset        = $pagination->offset();
            $sql_total        = "select count(*) from {$this->config['table']} b where {$where}";
            $pagination->setTotal($wpdb->get_var($sql_total));
            $limitstr = " limit {$offset},{$row_per_page} ";
        }



        $columns    = $wpdb->get_col("explain {$this->config['table']}");
        $orderby    = in_array('position', $columns) ? 'position asc' : 'ID asc';
        if (isset($this->config['orderby'])) {
            $orderby = $this->config['orderby'];
        }
        $sql    = "select * 
            from {$this->config['table']} 
            where {$where}
            order by {$orderby}
            {$limitstr}";

        $this->config['data'] = $wpdb->get_results($sql, ARRAY_A);
    }

    public function show($ajax = false)
    {
        global $wpdb;
        $this->config['url'] = $this->url;
        if (!isset($this->config['data'])) {
            $this->loaddata();
        }

        $need_prepares  = array();
        $replace_columns = array();
        $desc_options   = array();
        $filters        = array();

        foreach ($this->config['columns'] as $colkey => $carr) {
            if (isset($carr['filter']) && $carr['filter']) {
                $filters[] = $colkey;
            }
            if (($carr['type'] == 'selectm' || $carr['type'] == 'select2m') && !empty($carr['split']) && (!isset($need_prepares[$colkey]) || !is_array($need_prepares[$colkey]))) {
                $need_prepares[$colkey] = $carr['split'];
            }

            if (isset($carr['replaces']) && is_array($carr['replaces'])) {
                $re_search = array();
                $re_replace = array();
                foreach ($carr['replaces'] as $sk => $svl) {
                    $re_search[] = $sk;
                    $re_replace[] = $svl;
                }
                $replace_columns[$colkey] = array(
                    0 => $re_search,
                    1 => $re_replace,
                );
            }

            if ($carr['type'] == 'desc' && isset($carr['options']) && count($carr['options']) > 0) {
                $desc_options[$colkey] = $carr['options'];
            }
        }


        if (count($need_prepares) > 0 || count($replace_columns) > 0 || count($desc_options) > 0) {
            foreach ($this->config['data'] as $i => $row) {

                if (!is_array($row)) {
                    $row  = (array) $row;
                }
                foreach ($need_prepares as $colkey => $split) {

                    if (is_string($row[$colkey])) {
                        $row[$colkey] = explode($split, $row[$colkey]);
                        $this->config['data'][$i][$colkey] = $row[$colkey];
                    }
                }

                foreach ($replace_columns as $colkey => $replaces) {
                    if (is_string($row[$colkey])) {
                        $row[$colkey] = str_replace($replaces[0], $replaces[1], $row[$colkey]);
                        $this->config['data'][$i][$colkey] = $row[$colkey];
                    }
                }

                foreach ($desc_options as $colkey => $opts) {
                    $row[$colkey] = isset($opts[$row[$colkey]]) ? $opts[$row[$colkey]] : '';
                    $this->config['data'][$i][$colkey] = $row[$colkey];
                }
            }
        }


        $version = time();
        if (!$ajax) {
            $ajax = isset($_REQUEST['trajax']) || isset($_REQUEST['ctrajax']);
        }
        $baseurl = plugins_url('/', dirname(__FILE__));
        if ($ajax) {
            $this->config['fajax'] = $_REQUEST['trajax'];
            echo '<link media="all" rel="stylesheet" href="' . $baseurl . 'css/admin_table.css?v=' . $version . '" />';
        } else {
            wp_enqueue_style('admin_table', $baseurl . 'css/admin_table.css', array(), $version);
        }

        if (count($filters) > 0) {
            $this->show_filter($filters);
        }
    ?>
        <?php if (isset($pagination)) {
            echo '<div class="tablenav top">';
            echo $pagination->pagination(array('m'));
            echo '</div>';
        } ?>
        <div id="<?php echo $this->config['id'] ?>"></div>
        <?php if (isset($pagination)) {
            echo '<div class="tablenav bottom">';
            echo $pagination->pagination(array('m'));
            echo '</div>';
        } ?>
        <style>
            th.sortable {
                cursor: pointer;
                user-select: none;
                position: relative;
            }

            th.sortable .sort-icon {
                margin-left: 5px;
                opacity: 0.3;
            }

            th.sortable:hover .sort-icon {
                opacity: 0.6;
            }

            th.sortable.sort-asc .sort-icon i:before {
                content: "\f142";
                opacity: 1;
            }

            th.sortable.sort-desc .sort-icon i:before {
                content: "\f140";
                opacity: 1;
            }

            th.sortable.sort-asc .sort-icon,
            th.sortable.sort-desc .sort-icon {
                opacity: 1;
            }
        </style>
        <script src="<?php echo $baseurl . 'js/admin_table.js?v=' . time() ?>"></script>
        <script>
            jQuery(function($) {
                var $tableconfig = <?php echo json_encode($this->config) ?>;
                new Tr_Table('<?php echo $this->config['id'] ?>', $tableconfig);
            });
        </script>
        <?php
    }

    public function show_filter($filters)
    {
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . $_GET['page'] . '"/>';
        echo '<input type="hidden" name="tab" value="' . $_GET['tab'] . '"/>';
        echo '<input type="hidden" name="subtab" value="' . $_GET['subtab'] . '"/>';

        foreach ($filters as $fk) {
            $col = $this->config['columns'][$fk];
            $fvl = isset($this->filteredbys[$fk]) ? $this->filteredbys[$fk] : '';
            if (in_array($col['type'], array('select', 'select2m'))) {
                echo '<select name="filters[' . $fk . ']" class="js_select2" placeholder="' . $col['label'] . '">';
                echo '<option value="">All</option>';
                foreach ($col['options'] as $k => $txt) {
                    $selected = $k == $fvl ? 'selected' : '';
                    echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                }
                echo '</select>';
            } else {
                echo '<input type="text" name="filters[' . $fk . ']" value="' . $fvl . '" placeholder="' . $col['label'] . '">';
            }
        }
        echo '<input type="submit" class="button" value="Filter"/>';
        echo '</form>';
    }

    public function show_backups()
    {
        if (!file_exists($this->bk_folder)) {
            wp_mkdir_p($this->bk_folder);
        }

        $files = scandir($this->bk_folder, SCANDIR_SORT_DESCENDING);


        if (count($files) > 2) {
            echo '<table id="backupstbl" class="edit_tbl wp-list-table widefat striped posts hover_tbl ">';
            foreach ($files as $file) {
                if (!in_array($file, array('.', '..')) && stripos($file, '.meta') === false) {
                    $url = $this->bk_url . '/' . $file;
                    $filename = str_replace(array('_', '.csv'), array(' ', ''), $file);
                    $filename = preg_replace("/([0-9]{2})\s([0-9]{2})\s([0-9]{2})$/", '$1:$2:$3', $filename);
                    $restore_url = Tr_Base_Class_V6::link(array('trajax' => 'restore', 'f' => $file), false);
                    echo '<tr><td>';
                    echo '<a target=_blank href="' . $url . '">' . $filename . '</a>';
                    echo '</td><td>';
                    echo '<a class="restore" data-file="' . $file . '" href="' . $restore_url . '">Restore</a>';
                    echo '</td><td>';
                    echo '<a class="delete" data-file="' . $file . '" href="#">Delete</a>';
                    echo '</td></tr>';
                }
            }
            echo '</table>';
        ?>
            <script>
                jQuery(function($) {
                    $('#backupstbl .restore').on('click', function() {
                        if (!confirm('Are you sure to restore ' + $(this).data('file') + '?')) {
                            return false;
                        }
                    })
                    $('#backupstbl .delete').on('click', function() {
                        if (!confirm('Are you sure to delete file ' + $(this).data('file') + '?')) {
                            return false;
                        }
                        let $tr = $(this).parents('tr');
                        $.ajax({
                            data: {
                                trajax: 'remove_bk_file',
                                f: $(this).data('file')
                            },
                            type: 'post',
                            dataType: 'json',
                            success: function(json) {

                            }
                        });
                        $tr.remove();
                        return false;
                    })
                })
            </script>
<?php
        } else {
            $url = Tr_Base_Class_V6::link(array('trajax' => 'backup'), false);
            echo '<h2 style="text-align:center">Not found!</h2><br>';
            echo '<div style="text-align:center"><a class="button thickbox" href="' . $url . '">create a backup</a></div>';
        }

        exit;
    }

    public function restore()
    {
        global $wpdb;

        $file = $_REQUEST['f'];
        if (!empty($file)) {
            $file_path = $this->bk_folder . '/' . $file;
            if (file_exists($file_path)) {
                $f = fopen($file_path, 'r');
                if ($f) {
                    $header = array();
                    $wpdb->query("truncate table {$this->config['table']}");
                    while ($row = fgetcsv($f)) {
                        if (empty($header)) {
                            $header = $row;
                            continue;
                        }

                        $datarow = array();
                        foreach ($header as $i => $k) {
                            $datarow[$k] = $row[$i];
                        }

                        $wpdb->insert($this->config['table'], $datarow);
                    }
                }
            }

            //check meta
            $meta_path = $file_path . '.meta';
            if (file_exists($meta_path)) {
                $metadata = json_decode(file_get_contents($meta_path), true);
                if (isset($this->config['meta_dir']) && isset($metadata['files']) && is_array($metadata['files'])) {

                    foreach (scandir($this->config['meta_dir']) as $fname) {
                        if (in_array($fname, array('.', '..')) || stripos($fname, 'csv.log') > 0) continue;
                        $fname = str_replace('.deleted', '', $fname);
                        $fnamepath = $this->config['meta_dir'] . $fname;
                        if (in_array($fname, $metadata['files'])) {
                            if (!file_exists($fnamepath)) {
                                rename($fnamepath . '.deleted', $fnamepath);
                            }
                        } else {
                            if (file_exists($fnamepath)) {
                                rename($fnamepath, $fnamepath . '.deleted');
                            }
                        }
                    }
                }
            }

            Tr_Session_Class::add('Restored successfully!');
        }
        $url = Tr_Base_Class_V6::link(array('trajax' => '', 'f' => '', 'random' => ''), false);
        wp_redirect($url);
        exit;
    }

    public function remove_bk_file($file = '')
    {
        $byrequest = false;
        if (isset($_REQUEST['f']) && !empty($_REQUEST['f'])) {
            $byrequest = true;
            $file = $_REQUEST['f'];
        }

        if (!empty($file)) {
            $file_path = $this->bk_folder . '/' . $file;
            $return = array('status' => 'ok');
            @unlink($file_path);
            @unlink($file_path . '.meta');

            if ($byrequest) {
                wp_send_json($return);
            }
        }
    }
}
