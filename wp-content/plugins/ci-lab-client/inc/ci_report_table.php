<?php
class CI_Report_Table
{

    public $version     = '1.2';
    public $url         = '';
    public $config      = array();
    public $hide_header = false;
    public $columns     = array();
    public $rows        = array();
    public $filters     = array();
    public $tabs        = array();
    public $current_tab = '';

    public function __construct($config = array())
    {
        $this->config = $config;
    }

    public function setHeader($cols)
    {
        $this->columns = $cols;
    }

    public function addRow($row)
    {
        $this->rows[] = $row;
    }

    public function setData($data)
    {
        $this->rows = $data;
    }

    public function addTab($tab, $title = '')
    {
        if ($tab == 'btypes') {
            global $ci_all_btypes;
            foreach ($ci_all_btypes as $btype) {
                $this->tabs[$btype] = tr_pdd_get_typename($btype);
            }
        } else {
            $this->tabs[$tab] = $title;
        }
    }

    public function get_tab()
    {
        $this->current_tab = isset($_GET['tab']) ? $_GET['tab'] : array_key_first($this->tabs);

        return $this->current_tab;
    }

    public function addFilter($key, $type, $name, $default = '')
    {
        $vl = isset($_GET[$key]) ? $_GET[$key] : $default;
        $this->filters[$key] = array(
            'type' => $type,
            'name' => $name,
            'value' => $vl
        );
        return $vl;
    }

    public function showtabs() {}

    public function showfilter()
    {
        if (empty($this->filters)) {
            return;
        }
        echo '<div class="filters tablenav top">';
        echo '<form class="alignleft actions">';
        echo '<input type="hidden" name="page" value="' . $_GET['page'] . '"/>';
        if (isset($_GET['tab'])) {
            echo '<input type="hidden" name="tab" value="' . $_GET['tab'] . '"/>';
        }
        foreach ($this->filters as $key => $arr) {

            echo '<span class="fitlerblock filter_' . $key . '">';

            switch ($arr['type']) {
                case 'checkbox':
                    echo '<label>';
                    echo '<input type="hidden" name="' . $key . '" value=0/>';
                    echo '<input type="checkbox" name="' . $key . '" value=1 ' . checked('1', $arr['value'], false) . '/>';
                    echo   $arr['name'] . '</label>';
                    break;

                case 'date':
                    echo '<label>' . $arr['name'];
                    echo ' <input type="search" class="hasdate" name="' . $key . '" value="' . $arr['value'] . '"/> ';
                    echo '</label>';
                    break;
            }

            echo '</span>';
        }
        echo ' <input type="submit" class="button-primary" value="Filter"/> ';
        if (isset($this->config['export']) && $this->config['export']) {
            echo ' <input type="submit" class="button" name="trajax" value="Export"/> ';
        }
        echo '</form>';
        if (isset($this->config['pagetive'])) {
            echo '<div class="tablenav-pages no-pages">';
            echo '</div>';
        }
        echo '</div>';
    }

    public function export()
    {
        $fname = str_replace(' ', '_', strtolower($this->config['title'])) . '.csv';
        tr_pdd_header('csv', $fname);
        $f = fopen('php://output', 'w');
        fputcsv($f, $this->columns);

        foreach ($this->rows as $row) {
            $rowdata = array();
            foreach ($this->columns as $k => $txt) {
                $arr = is_array($row[$k]) ? $row[$k] : array('label' => $row[$k]);

                $rowdata[] = $arr['label'];
            }

            fputcsv($f, $rowdata);
        }
        exit;
    }

    public function display()
    {
        if (isset($_GET['trajax']) && stripos($_GET['trajax'], 'export') !== false) {
            $this->export();
            exit;
        }
        $columns = $this->columns;
        $table_classes = 'cireport_tbl ';
        echo '<div class="wrap">';
        if (isset($this->config['title'])) {
            if (count($this->tabs) > 1) {
                $this->showtabs();
            } else {
                echo '<h2>' . $this->config['title'] . '</h2>';
            }

            Tr_Session_Class::show();
            $table_classes .= ' report_tbl ';
        }

        $this->showfilter();
        echo '<table class="' . $table_classes . '">';
        if (count($this->columns) > 0) {
            echo '<thead>';
            echo '<tr>';
            foreach ($columns as $col) {
                $label = is_array($col) ? $col['label'] : $col;
                $cclass = is_array($col) && !empty($col['class']) ? $col['class'] : '';
                echo '<th class="' . $cclass . '">' . $label . '</th>';
            }
            echo '</tr>';
            echo '</thead>';
        } else {
            $columns = $this->rows[0];
        }


        foreach ($this->rows as $row) {
            echo '<tr class="childp">';
            foreach ($columns as $k => $txt) {
                $arr = is_array($row[$k]) ? $row[$k] : array('label' => $row[$k]);
                $style = '';
                if (isset($arr['width']) && !empty($arr['width'])) {
                    $style .= ';width:' . $arr['width'];
                }

                $cclass = isset($arr['class']) && !empty($arr['class']) ? $arr['class'] : '';

                $atts = '';
                if (!empty($style)) $atts .= ' style="' . $style . '"';
                if (!empty($cclass)) $atts .= ' class="' . $cclass . '"';
                echo '<td  ' . $atts . '>';
                echo $arr['label'];
                echo '</td>';
            }

            echo '</tr>';
        }
        echo '</table>';

        echo '</div>';
    }
}
