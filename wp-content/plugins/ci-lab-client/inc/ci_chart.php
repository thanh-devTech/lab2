<?php
class CI_Chart
{

    public static $showed_canvasjs = false;
    public static $showed_googlejs = false;
    public static $last_chartid = 0;

    public $chartsource   = 'google'; //or canvasjs
    public $charttype   = '';
    public $data        = array();

    public $id          = 'divchart';

    public $monthdata   = array();
    public $current_month = '';

    public $config = array();


    /*
    config:
    - chartsource: google / canvasjs
    - chartype: doughnut
    */
    public function __construct($config = array())
    {
        $this->id = 'chart_' . self::$last_chartid++;
        if (!isset($config['width']) || empty($config['width'])) {
            $config['width'] = 500;
        }
        if (!isset($config['height']) || empty($config['height'])) {
            $config['height'] = 300;
        }
        $this->config = $config;
        $this->chartsource = !empty($config['chartsource']) ? $config['chartsource'] : 'google';
        $this->charttype = !empty($config['charttype']) ? $config['charttype'] : 'doughnut';
    }

    public function setTitle($title)
    {
        $this->config['title'] = $title;
    }

    public function setName_Unit($name, $unit)
    {
        $this->data = array();
        $this->data[] = func_get_args();
    }

    public function addData($name, $value)
    {
        if ($this->chartsource == 'canvasjs') {
            $this->data[] = array(
                'y' => $value,
                'label' => $name,
            );
        } else {
            if (empty($this->data)) {
                $h = array();
                for ($i = 0; $i < count(func_get_args()); $i++) {
                    $h[] = '';
                }
                $this->data[] = $h;
            }
            $this->data[] = func_get_args();
        }
    }

    public function addMonthData($name, $value)
    {
        $this->monthdata[] = func_get_args();
    }


    public function setupMonthSelect($target, $displayid='')
    {

        if (empty($this->current_month)) {
            $this->current_month = end($this->monthdata)[0];
        }
?>
        <script>
            jQuery(function($) {
                var $displayid = '<?php echo $displayid?>';
                $('<?php echo $target ?>').on('click', function(e) {
                    if (e.target.tagName != 'SPAN') return;
                    var $html = '<select>';
                    var data = <?php echo json_encode($this->monthdata) ?>;
                    for (i in data) {
                        var $selected = data[i][0] == '<?php echo $this->current_month ?>' ? 'selected' : '';
                        $html += '<option value="' + data[i][0] + '" ' + $selected + '>' + data[i][0] + '</option>';
                    }
                    $html += '</select>';
                    $(this).html($html);
                    $('select', $(this)).focus().click();

                    $('select', $(this)).change(function() {
                        var $mname = $(this).val();
                        for (i in data) {
                            if (data[i][0] == $mname) {
                                var $newdata = data[i][1];
                                <?php echo 'drawChart' . $this->id ?>($newdata);
                                break;
                            }
                        }
                        if($displayid && $mname)
                        {
                            $('#'+$displayid).html($mname);
                        }
                    })
                })
            })
        </script>
    <?php
    }

    public function show_chart_canvasjs()
    {
        if (!self::$showed_canvasjs) {
            echo '<script src="' . TRLCN_URL . 'js/canvasjs.min.js"></script>';
            self::$showed_canvasjs = true;
        }
    ?>
        <div id="<?php echo $this->id ?>" style="max-width:<?php echo $this->config['width'] . 'px' ?>;height:<?php echo $this->config['height'] . 'px' ?>"></div>
        <script>
            var $data = <?php echo json_encode($this->data) ?>;
            var chart = new CanvasJS.Chart("<?php echo $this->id ?>", {
                animationEnabled: true,
                title: {
                    text: '<?php echo $this->config['title'] ?>'
                },
                borderWidth: 5,
                data: [{
                    type: "<?php echo $this->charttype ?>",
                    startAngle: 240,
                    yValueFormatString: "##0.00\"%\"",
                    indexLabel: "{label} {y}",
                    dataPoints: $data
                }]
            });
            chart.render();
        </script>
    <?php
    }

    public function show_chart_google()
    {
        if (!self::$showed_googlejs) {
            echo '<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>';
            self::$showed_googlejs = true;
        }
        $ctype = 'PieChart';
        if ($this->charttype == 'column') {
            $ctype = 'ColumnChart';
        }

        $options = array(
            'title' => $this->config['title'],
            'titleTextStyle' => array(
                'fontSize' => 14
            ),
            'pieHole' => 0.4,
            'pieSliceText' => $this->config['pieSliceText'] . '',
            'legend' => array(
                'position' => 'right',
                'maxLines' => 15,
                'textStyle' => array(
                    'fontSize' => 11,
                    'bold' => false
                )
            ),
            'chartArea' => array(
                'width' => '100%'
            )
        );
        if(isset($this->config['colors']) && is_array($this->config['colors'])){
            $options['colors'] = $this->config['colors'];
        }
        if ($this->config['isStacked']) {
            $options['isStacked'] = $this->config['isStacked'];
        }
    ?>
        <div id="<?php echo $this->id ?>" style="max-width:<?php echo $this->config['width'] . 'px' ?>;height:<?php echo $this->config['height'] . 'px' ?>"></div>

        <script>
            var <?php echo $this->id ?> = null;
            var <?php echo $this->id . 'options' ?> = <?php echo json_encode($options) ?>;

            function <?php echo 'drawChart' . $this->id ?>(data) {
                console.log(data);

                var dataChart = google.visualization.arrayToDataTable(data);

                <?php echo $this->id ?> = new google.visualization.<?php echo $ctype ?>(document.getElementById('<?php echo $this->id ?>'));
                <?php echo $this->id ?>.draw(dataChart, <?php echo $this->id . 'options' ?>);
            }

            function <?php echo 'chart_callback' . $this->id ?>() {
                <?php echo 'drawChart' . $this->id ?>(<?php echo json_encode($this->data) ?>);
            }

            google.charts.load("current", {
                packages: ["corechart"]
            });
            google.charts.setOnLoadCallback(<?php echo 'chart_callback' . $this->id ?>);
        </script>
<?php
    }


    public function display()
    {
        if ($this->chartsource == 'canvasjs') {
            $this->show_chart_canvasjs();
        } else {
            $this->show_chart_google();
        }
    }
}
