function tr_pdd_standard_deviation(values) {
    var avg = average(values);

    var squareDiffs = values.map(function (value) {

        var diff = value - avg;
        var sqrDiff = diff * diff;
        return sqrDiff;
    });
    var avgSquareDiff = average(squareDiffs);

    var stdDev = Math.sqrt(avgSquareDiff);
    return stdDev;
}
function tr_round(value, ndec) {
    var n = 10;
    for (var i = 1; i < ndec; i++) {
        n *= 10;
    }

    if (!ndec || ndec <= 0)
        return Math.round(value);
    else
        return Math.round(value * n) / n;
}
function average(data) {
    var sum = data.reduce(function (sum, value) {
        return sum + value;
    }, 0);
    var avg = sum / data.length;
    return avg;
}
Chart.types.Line.extend({
    name: "LineWithBackground",
    draw: function () {
        Chart.types.Line.prototype.draw.apply(this, arguments);

        var lines = this.options.limitLines;

        for (var i = lines.length; --i >= 0;) {

            var xStart = Math.round(this.scale.xScalePaddingLeft);
            var linePositionY = this.scale.calculateY(lines[i].value);

            //$lwidth = 30;
            this.chart.ctx.fillStyle = lines[i].color ? lines[i].color : this.scale.textColor;
            this.chart.ctx.font = this.scale.font;
            this.chart.ctx.textAlign = "left";
            this.chart.ctx.textBaseline = "top";



            this.chart.ctx.lineWidth = this.scale.gridLineWidth;;
            this.chart.ctx.strokeStyle = lines[i].color ? lines[i].color : this.scale.gridLineColor;

            if (this.scale.showHorizontalLines) {
                var $has_line = true;
                if (lines[i].rect) {
                    if (lines[i].tlabel || lines[i].db) {
                        var y2 = this.scale.calculateY(lines[i].rect);
                        $h = linePositionY - y2;
                    } else {
                        var y2 = linePositionY;
                        var y3 = this.scale.calculateY(lines[i].rect);
                        if (y3 > this.scale.height - 20) {
                            // y3 =this.scale.height -20;
                        }
                        $h = y3 - y2;

                    }
                    if ($h > 0) {
                        this.chart.ctx.fillRect(xStart, y2, this.scale.width, $h * ((lines[i].db) ? 2 : 1));
                        this.chart.ctx.stroke();
                    }

                    $has_line = lines[i].db ? true : false;
                }

                if ($has_line) {
                    if (lines[i].lcolor) {
                        this.chart.ctx.strokeStyle = lines[i].lcolor;
                    }
                    this.chart.ctx.beginPath();
                    this.chart.ctx.moveTo(xStart, linePositionY);
                    this.chart.ctx.lineTo(this.scale.width, linePositionY);
                    this.chart.ctx.stroke();
                    this.chart.ctx.closePath();
                }

            }
            if (this.scale.showLabels && lines[i].label) {
                this.chart.ctx.fillStyle = this.scale.textColor;
                var $ay = lines[i].tlabel ? -12 : 1;
                this.chart.ctx.fillText(lines[i].label, this.scale.width - 55, linePositionY + $ay);
            }


        }

        var ctx = this.chart.ctx;
        ctx.font = this.scale.font;
        ctx.fillStyle = this.scale.textColor
        ctx.textAlign = "center";
        ctx.textBaseline = "bottom";
        var $ci = 0;
        this.datasets.forEach(function (dataset) {
            $ci++;
            if ($ci > 1) return;
            var $i = 0;
            dataset.points.forEach(function (bar) {
                var x = $i == 0 ? bar.x + 20 : bar.x;
                ctx.fillText(bar.value, x, bar.y - 10);
                $i++;
            });
        })
    }
});

Chart.types.Line.extend({
    name: "LineWithLine",
    draw: function () {
        Chart.types.Line.prototype.draw.apply(this, arguments);

        var lines = this.options.limitLines;

        for (var i = lines.length; --i >= 0;) {

            var xStart = Math.round(this.scale.xScalePaddingLeft);
            var linePositionY = this.scale.calculateY(lines[i].value);

            this.chart.ctx.fillStyle = lines[i].color ? lines[i].color : this.scale.textColor;
            this.chart.ctx.font = this.scale.font;
            this.chart.ctx.textAlign = "left";
            this.chart.ctx.textBaseline = "top";

            if (this.scale.showLabels && lines[i].label) {
                this.chart.ctx.fillText(lines[i].label, this.scale.width - 55, linePositionY - 12);
            }

            this.chart.ctx.lineWidth = this.scale.gridLineWidth;
            this.chart.ctx.strokeStyle = lines[i].color ? lines[i].color : this.scale.gridLineColor;
            if (lines[i].linetype) {
                if (lines[i].linetype == 'dot') {
                    this.chart.ctx.setLineDash([2, 3]);
                } else if (lines[i].linetype == 'dashed') {
                    this.chart.ctx.setLineDash([7, 5]);
                }
            } else {
                this.chart.ctx.setLineDash([]);
            }

            if (this.scale.showHorizontalLines) {
                this.chart.ctx.beginPath();
                this.chart.ctx.moveTo(xStart, linePositionY);
                this.chart.ctx.lineTo(this.scale.width, linePositionY);
                this.chart.ctx.stroke();
                this.chart.ctx.closePath();
            }

            this.chart.ctx.lineWidth = this.lineWidth;
            this.chart.ctx.strokeStyle = this.lineColor;


            this.chart.ctx.beginPath();
            this.chart.ctx.moveTo(xStart - 5, linePositionY);
            this.chart.ctx.lineTo(xStart, linePositionY);
            this.chart.ctx.stroke();
            this.chart.ctx.closePath();
        }

        var ctx = this.chart.ctx;
        ctx.font = this.scale.font;
        ctx.fillStyle = this.scale.textColor
        ctx.textAlign = "center";
        ctx.textBaseline = "bottom";
        var $ci = 0;
        if (this.options.ylabel) {
            ctx.save();
            ctx.translate(0, 0);
            ctx.rotate(- Math.PI / 2);
            ctx.fillText(this.options.ylabel, -150, 15);
            ctx.restore();
        }
        this.datasets.forEach(function (dataset) {
            $ci++;
            if ($ci > 1) return;
            var $i = 0;
            dataset.points.forEach(function (bar) {
                var x = $i == 0 ? bar.x + 20 : bar.x;
                ctx.fillText(bar.value, x, bar.y - 10);
                $i++;
            });
        })
    }
});

function setup_chat_bg($key, $data) {
    var $cvobj = jQuery('#curve_chart' + $key);
    if (typeof $data.center == 'undefined') {
        $data.center = $cvobj.data('center');
    }
    if (typeof $data.is_percent == 'undefined') {
        $data.is_percent = $cvobj.data('is_percent') || false;
    }
    if (typeof $data.average == 'undefined') {
        $data.average = $cvobj.data('average');
    }

    if ($data.charttype && $data.charttype == 'high_low') {
        var $arg = ($data.values.reduce(function (a, b) { return a + b; }, 0)) / $data.values.length;
        $a1 = $a2 = $a3 = $data.high_cutoff;
        $b1 = $b2 = $b3 = $data.low_cutoff;

        $sigma = tr_pdd_standard_deviation($data.values);
        $arg = ($data.values.reduce(function (a, b) {
            return a + b;
        }, 0)) / $data.values.length;
        $arg = tr_round($arg, 3);
        $a3 = tr_round($arg + 3 * $sigma, 3);
        $a2 = tr_round($arg + 2 * $sigma, 3);
        $a1 = tr_round($arg + 1 * $sigma, 3);
        $b1 = tr_round($arg - 1 * $sigma, 3);
        $b2 = tr_round($arg - 2 * $sigma, 3);
        $b3 = tr_round($arg - 3 * $sigma, 3);
        var $limitline = [
            {
                label: $arg,
                value: $arg,
                color: $colors[0],
            },
            {
                label: $a1,
                value: $a1,
                color: $colors['high']
            },
            {
                label: $b1,
                value: $b1,
                color: $colors['low']
            },
        ];
        $limitline = [
            {
                label: $arg,
                value: $arg,
                rect: $a1,
                db: true,
                color: $colors[0],
                lcolor: $colors['center']
            }, {
                label: $a3,
                tlabel: true,
                value: $a3,
                rect: $a3 * 2,
                color: $colors[3]
            },
            {
                label: $a2,
                tlabel: true,
                value: $a2,
                rect: $a3,
                color: $colors[2]
            },
            {
                value: $a1,
                tlabel: true,
                rect: $a2,
                color: $colors[1]
            },
            {
                value: $b1,
                rect: $b2,
                color: $colors[1]
            },
            {
                label: $b2,
                value: $b2,
                rect: $b3,
                color: $colors[2]
            },
            {
                label: $b3,
                value: $b3,
                rect: 0.001,
                color: $colors[3]
            }
        ];
    }
    else if ($data.center == '' || !$data.is_percent) {
        $sigma = tr_pdd_standard_deviation($data.values);
        $arg = ($data.values.reduce(function (a, b) {
            return a + b;
        }, 0)) / $data.values.length;
        $arg = tr_round($arg, 3);
        $a3 = tr_round($arg + 3 * $sigma, 3);
        $a2 = tr_round($arg + 2 * $sigma, 3);
        $a1 = tr_round($arg + 1 * $sigma, 3);
        $b1 = tr_round($arg - 1 * $sigma, 3);
        $b2 = tr_round($arg - 2 * $sigma, 3);
        $b3 = tr_round($arg - 3 * $sigma, 3);

        $limitline = [
            {
                label: $arg,
                value: $arg,
                rect: $a1,
                db: true,
                color: $colors[0],
                lcolor: $colors['center']
            }, {
                label: $a3,
                tlabel: true,
                value: $a3,
                rect: $a3 * 2,
                color: $colors[3]
            },
            {
                label: $a2,
                tlabel: true,
                value: $a2,
                rect: $a3,
                color: $colors[2]
            },
            {
                value: $a1,
                tlabel: true,
                rect: $a2,
                color: $colors[1]
            },
            {
                value: $b1,
                rect: $b2,
                color: $colors[1]
            },
            {
                label: $b2,
                value: $b2,
                rect: $b3,
                color: $colors[2]
            },
            {
                label: $b3,
                value: $b3,
                rect: 0.001,
                color: $colors[3]
            }
        ];
    } else {
        var $arg = $data.center;
        var $min = Math.min.apply(Math, $data.values);
        var $max = Math.max.apply(Math, $data.values);
        var $jg = ($max - $min) / 4;
        var $a1 = $a3 = tr_round($max + $jg, 3);
        var $b1 = $b3 = tr_round($min - $jg, 3);

        $limitline = [
            {
                label: $arg,
                value: $arg,
                lcolor: $colors[0]
            },
            {
                tlabel: true,
                value: $max,
                rect: $a1,
                color: $colors[25]
            },

            {
                value: $min,
                rect: $b1,
                color: $colors[25]
            }
        ];
    }

    var $icount = 1;
    var $datasets = [{
        label: $data.label,
        strokeColor: $data.color,
        pointColor: $data.color,
        data: $data.values
    }];
    if ($data.values2) {
        $datasets[$icount++] = {
            label: $data.label,
            strokeColor: $data.color2,
            pointColor: $data.color2,
            data: $data.values2
        };
    }
    if ($data.values3) {
        $datasets[$icount++] = {
            label: $data.label,
            strokeColor: $data.color3,
            pointColor: $data.color3,
            data: $data.values3
        };
    }
    $datasets[$icount++] = {
        label: '',
        strokeColor: "rgba(200, 200, 200, .1)",
        pointColor: $colors[0],
        data: [$arg]
    };
    $datasets[$icount++] = {
        label: '+3',
        strokeColor: "rgba(200, 200, 200, .1)",
        pointColor: "rgba(200, 200,200, .1)",
        data: [$a3]
    };
    $datasets[$icount++] = {
        label: '-3',
        strokeColor: "rgba(200, 200,200, .1)",
        pointColor: "rgba(200, 200, 200, .1)",
        data: [$b3]
    }
    var data = {
        labels: $data.dates,
        datasets: $datasets
    };

    $new = jQuery('#curve_chart' + $key).clone();
    jQuery('#curve_chart' + $key).replaceWith($new);
    var ctx = document.getElementById('curve_chart' + $key).getContext("2d");


    var $chart = new Chart(ctx).LineWithBackground(data, {
        datasetFill: false,
        bezierCurve: false,
        animation: false,
        limitLines: $limitline
    });
}

function setup_chat_line($key, $data) {
    console.log('setup_chat_line');
    var $cvobj = jQuery('#curve_chart' + $key);
    if (typeof $data.center == 'undefined') {
        $data.center = $cvobj.data('center');
    }
    if (typeof $data.is_percent == 'undefined') {
        $data.is_percent = $cvobj.data('is_percent') || false;
    }
    if (typeof $data.average == 'undefined') {
        $data.average = $cvobj.data('average');
    }


    if ($data.charttype && $data.charttype == 'high_low') {
        var $arg = ($data.values.reduce(function (a, b) { return a + b; }, 0)) / $data.values.length;
        var $a1 = $a2 = $data.high_cutoff;
        var $b1 = $b2 = $data.low_cutoff;

        console.log("high_cutoff: " + $data.high_cutoff);
        console.log("low_cutoff: " + $data.low_cutoff);
        var $limitline = [
            {
                label: $arg,
                value: $arg,
                color: $colors[0],
            },
            {
                label: $a1,
                value: $a1,
                color: $colors['High']
            },
            {
                label: $b1,
                value: $b1,
                color: $colors['Low']
            },
        ];
    }
    else if ($data.center == '' || !$data.is_percent) {
        var $arg = ($data.values.reduce(function (a, b) { return a + b; }, 0)) / $data.values.length;
        $sigmarr = $data.values;

        $arg = tr_round($arg, 3);
        if ($data.average > 0) {
            $arg = $data.average;

            $sigmarr.push($data.center);
        }

        var $sigma = tr_pdd_standard_deviation($sigmarr);


        console.log("$arg: " + $arg);
        console.log("$sigma: " + $sigma);
        var $a2 = tr_round($arg + 2 * $sigma, 3);
        var $a1 = tr_round($arg + 1 * $sigma, 3);
        var $b1 = tr_round($arg - 1 * $sigma, 3);
        var $b2 = tr_round($arg - 2 * $sigma, 3);
        var $limitline = [
            {
                label: $arg,
                value: $arg,
                color: $colors[0],
            },
            {
                label: $a2,
                value: $a2,
                linetype: 'dot',
                color: $colors[2]
            },
            {
                label: $a1,
                value: $a1,
                linetype: 'dashed',
                color: $colors[1]
            },
            {
                label: $b1,
                value: $b1,
                linetype: 'dashed',
                color: $colors[1]
            },
            {
                label: $b2,
                value: $b2,
                linetype: 'dot',
                color: $colors[2]
            }
        ];
    }
    else {
        var $arg = $data.center;
        var $min = Math.min.apply(Math, $data.values);
        var $max = Math.max.apply(Math, $data.values);
        var $jg = ($max - $min) / 4;
        var $a1 = $a2 = tr_round($max + $jg, 3);
        var $b1 = $b2 = tr_round($min - $jg, 3);
        var $limitline = [
            {
                label: $arg,
                value: $arg,
                color: $colors[0],
            },
            {
                label: '+25%',
                value: $a1,
                color: $colors[25]
            },
            {
                label: '-25%',
                value: $b1,
                color: $colors[25]
            },
        ];
    }

    var $icount = 1;
    var $datasets = [{
        label: $data.label,
        strokeColor: $data.color,
        pointColor: $data.color,
        data: $data.values
    }];
    if ($data.values2) {
        $datasets[$icount++] = {
            label: $data.label,
            strokeColor: $data.color2,
            pointColor: $data.color2,
            data: $data.values2
        };
    }

    if ($data.values3) {
        $datasets[$icount++] = {
            label: $data.label,
            strokeColor: $data.color3,
            pointColor: $data.color3,
            data: $data.values3
        };
    }
    $datasets[$icount++] = {
        label: '',
        strokeColor: "rgba(200, 200, 200, .1)",
        pointColor: $colors[0],
        data: [$arg]
    };
    $datasets[$icount++] = {
        label: '+2',
        strokeColor: "rgba(200, 200, 200, .1)",
        pointColor: "rgba(200, 200, 200, .1)",
        data: [$a2]
    };
    $datasets[$icount++] = {
        label: '-2',
        strokeColor: "rgba(200, 200, 200, .1)",
        pointColor: "rgba(200, 200, 200, .1)",
        data: [$b2]
    }
    var data = {
        labels: $data.dates,
        datasets: $datasets
    };

    $new = $cvobj.clone();
    $cvobj.replaceWith($new);

    var ctx = document.getElementById('curve_chart' + $key).getContext("2d");


    var $chart = new Chart(ctx).LineWithLine(data, {
        datasetFill: false,
        bezierCurve: false,
        animation: false,
        ylabel: $data.label,
        limitLines: $limitline
    });
}