<?php
class CI_Table
{

    public $config;
    public $version = '1.2';
    public $url = '';

    public function __construct($cf)
    {
        $this->config = $cf;
        $this->config['id'] = time();
        $this->checkcolumns();

        add_thickbox();

        $act =  isset($_REQUEST['act']) && !empty($_REQUEST['act']) ? $_REQUEST['act'] : '';

        if (empty($act) && isset($_REQUEST['trajax'])) {
            $act = $_REQUEST['trajax'];
        }
        if (!empty($act)) {
            if ($act == 'save_table') {
                if (isset($this->config['save_func'])) {
                    call_user_func($this->config['save_func']);
                } else {
                    $this->save_table();
                }
            } else if ($act == 'remove_table_row') {
                $this->remove_table_row();
            } else if ($act == 'export') {
                $this->export();
            } else if ($act == 'import_frame') {
                $this->import_frame();
            }
        }
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

            if(isset($arr['options_by']) && $arr['options_by']){
                
                $this->config['columns'][$k]['optionkey'] = array_key_first($this->config['columns'][$arr['options_by']]['options']);
                $this->config['columns'][$arr['options_by']]['class'].=' ctrloptions ';
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
            $id = $_REQUEST['data']['ID'] ? $_REQUEST['data']['ID'] : 0;
            $data = array($id => $_REQUEST['data']);
        }
        return $data;
    }

    public function export()
    {
        $filename = $this->config['name'] . '.csv';
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        $f = fopen('php://output', 'w');

        $header = array();
        foreach ($this->config['columns'] as $arr) {
            $header[] = $arr['label'];
        }
        fputcsv($f, $header);

        foreach ($this->config['data'] as $i => $row) {
            $rowdata = array();
            foreach ($this->config['columns'] as $ckey => $arr) {
                $rowdata[$ckey] = $row[$ckey];
            }
            fputcsv($f, $rowdata);
        }
        exit;
    }

    public function import_frame()
    {
        global $wpdb;

        if (isset($_FILES['csv_file'])) {
            $f = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($f) {

                $cache_data = array();
                foreach ($this->config['columns'] as $ckey => $carr) {
                    $ckey = str_replace('colid_','',$ckey);
                    if (isset($carr['map'])) {
                        $cache_data[$ckey] = array();
                        $table      = $carr['map'][0];
                        $namekey    = $carr['map'][1];
                        $ikey       = $carr['map'][2];
                        $rs         = $wpdb->get_results("select {$namekey}, {$ikey} from {$table}");

                        foreach ($rs as $r) {
                            $cache_data[$ckey][$r->{$namekey}] = $r->{$ikey};
                        }
                        
                    }
                }
                


                $header = array();
                $wpdb->query("truncate table {$this->config['table']}");

                while ($row = fgetcsv($f)) {
                    if (empty($header)) {

                        //check header
                        if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
                            continue;
                        } else {
                            $header = array();
                            foreach ($row as $i => $tt) {
                                $header[strtolower($tt)] = $i;
                            }
                        }

                        continue;
                    } else {
                        $rows   = array();
                        $rowdata = array();
                        $multirows = array();

                        $inum = 0;
                        $empty_row = false;
                        
                        foreach ($this->config['columns'] as $ckey => $arr) {
                            
                            $ckey = str_replace('colid_','',$ckey);
                            
                            $colkey = strtolower($arr['label']);
                            $vl = $row[$header[$colkey]];
                            
                            
                            if (isset($arr['column'])) {
                                if (!isset($multirows[$arr['column']])) {
                                    $multirows[$arr['column']] = array();
                                }
                                if (!empty($vl)) {
                                    $colvl = $arr['label'];
                                    if (isset($arr['map'])) {
                                        $colvl = $cache_data[$ckey][$colvl];
                                    } 
                                    
                                    $multirows[$arr['column']][$colvl] = array($arr['mapto'], $vl);
                                }
                            }
                            else if (isset($arr['map'])) {
                                if (!isset($cache_data[$ckey][$vl])) {
                                    $empty_row = true;
                                    continue;
                                }
                                $rowdata[$ckey] = $cache_data[$ckey][$vl];
                            }  else  {
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
                            
                            $wpdb->insert($this->config['table'], $rowdata);
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
        $insert_all = false;
        $has_filter = isset($this->config['hasfilter']) && $this->config['hasfilter'];
        $has_limit  = isset($this->config['limit']) && $this->config['limit'] > 0;

        if ($this->config['save_all'] && isset($this->config['save_map'])) {

            if (!$has_filter && !$has_limit) {
                $insert_all = true;
                $wpdb->query("truncate table {$this->config['table']}");
            }
            $save_map = $this->config['save_map'];
            $column_by_map = true;

            foreach ($data as $k => $vl) {
                
                $row = array();
                $arr = explode('-', $k, count($save_map['key']));

                foreach ($save_map['key'] as $fk => $pos) {
                    $row[$fk] = str_replace('colid_','',$arr[$pos]);

                    if (!$insert_all) {
                        $check_exist[] = $fk;
                    }
                }

                if (isset($save_map['value'])) {
                    if ($insert_all && empty($vl)) {
                        continue;
                    }
                    $row[$save_map['value']] = $vl;
                }

                if (!isset($rows[$k])) {
                    $rows[$k] = $row;
                }
            }
        } else {
            $rows = $data;
        }

        foreach ($rows as $id => $row) {

            unset($row['ID']);

            if(isset($this->config['hide_fields']) && count($this->config['hide_fields'])>0)
            {
                foreach($this->config['hide_fields'] as $k => $vl)
                {
                    $row[$k] = $vl;
                }
            }

            if (!$column_by_map) {
                foreach ($this->config['columns'] as $k => $arr) {
                    if ($arr['type'] == 'select2m') {
                        if(isset($arr['split']) && !empty($arr['split'])){
                            $row[$k] = implode($arr['split'], $row[$k] );
                        }else{
                            $row[$k] = json_encode(is_array($row[$k]) ? $row[$k] : array());
                        }
                        
                    }
                    $row[$k] = stripslashes($row[$k]);
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

                $exist = $wpdb->get_var("select count(*) from {$this->config['table']}
                where 1 {$where_exist}");
                if (!$exist) {
                    $id = 0;
                    $updateby = array();
                } else {
                    $id = time();
                }
            }

            if ($insert_all || $id == 0 || stripos($id, 'new') !== false) {

                if(in_array('created',$columns))
                {
                    $row['created'] = date_i18n('Y-m-d H:i:s');
                }
                if(in_array('created_by',$columns))
                {
                    $row['created_by'] = get_current_user_id();
                }
                $wpdb->insert($this->config['table'], $row);
                $newid = $wpdb->insert_id;
            } else {
                $wpdb->update($this->config['table'], $row, $updateby);
                $newid = $id;
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

    public function show($ajax=false)
    {
        global $wpdb;
        $this->config['url'] = $this->url;
        if (!isset($this->config['data'])) {

            $columns    = $wpdb->get_col("explain {$this->config['table']}");
            $orderby = in_array('position',$columns)? 'position':'ID';
            $sql = "select * 
            from {$this->config['table']} 
            order by {$orderby} asc";
            $this->config['data'] = $wpdb->get_results($sql);
        }
        $need_prepares = array();
        foreach($this->config['columns'] as $colkey => $carr)
        {
            if(($carr['type']=='selectm' || $carr['type']=='select2m') && !empty($carr['split']) && !is_array($need_prepares[$colkey])){
                $need_prepares[$colkey] = $carr['split'];
            }
        }

        if(count($need_prepares)>0)
        {
            foreach($this->config['data'] as $i =>$row)
            {
                foreach($need_prepares as $colkey => $split)
                {
                    $this->config['data'][$i]->{$colkey} = explode($split, $row->{$colkey});
                }
            }
        }
        

        $version = time();
        if(!$ajax)
        {
            $ajax = isset($_REQUEST['trajax']) || isset($_REQUEST['ctrajax']);
        }
        $baseurl = plugins_url('/',dirname(__FILE__));
        if($ajax)
        {
            echo '<link media="all" rel="stylesheet" href="'.$baseurl.'css/admin_table.css?v='.$version.'" />';
        }
        else{
            wp_enqueue_style('admin_table',$baseurl.'css/admin_table.css',array(),$version);
        }
    ?>
        <div id="<?php echo $this->config['id'] ?>"></div>

        <script src="<?php echo $baseurl . 'js/admin_table.js?v=' . time() ?>"></script>
        <script>
            jQuery(function($) {
                var $tableconfig = <?php echo json_encode($this->config) ?>;
                new Tr_Table('<?php echo $this->config['id'] ?>', $tableconfig);
            });
        </script>
<?php
    }
}
