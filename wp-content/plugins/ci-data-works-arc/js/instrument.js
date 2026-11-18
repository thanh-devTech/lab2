var $connected = 0;
var $lastport = 0;
var $ = jQuery;


function TestClient($port) {
    var socket;
    var $this = this;
    var $intvlog;
    var $lastlogtime = 0;
    var clearlogserver = 0;
    var $text_wslog = '';
    var $type = '';
    var $tmp_msgs = [];
    var $need_send_eot = false;

    this.setType = function (type) {
        $type = type;
    }

    this.getType = function () {
        return $type;
    }

    this.connect = function () {
        if ($connected !== 0) {

            if ($lastport == $ws_port) {
                var $textarea = $('#ws_receving');
                var $text = $textarea.val();
                if ($text == '') {
                    $('#ws_receving').val($text_wslog);
                }
                return true;
            } else {
                socket.close();
                $text_wslog = '';
            }

        }
        $connected = 2;
        $lastport = $ws_port;
        socket = new WebSocket('wss://api.webhue.net/instrument');
        socket.addEventListener('open', function (event) {
            $connected = 1;
            socket.send('{"kind":"set_config","host":"' + $ws_host + '","port":' + $ws_port + '}');
        });

        socket.addEventListener('message', function (event) {
            console.log('Message from server ', event.data);
            try {
                var json = JSON.parse(event.data);

                if (json.kind == 'on_data') {
                    $this.addTextView('Rec', json.data);
                    if (json.data == '<ACK>') {
                        $msg = '';
                        if ($tmp_msgs.length == 0 && $need_send_eot) {
                            $msg = '<EOT>';
                            $need_send_eot = false;
                        }
                        $this.send($msg);
                    } else if (json.data == '<ENQ>') {
                        $this.send('<ACK>');
                    }
                    else if (json.data.length > 10) {
                        $this.send('<ACK>');
                    }
                } else if (json.kind == 'set_config') {
                    $this.addTextView('alert', json.status + ' port: ' + $ws_port);
                }
            } catch (e) {

            }


        });
        socket.addEventListener('close', function (event) {
            $connected = 0;
            setTimeout(function () {
                $this.connect();
            }, 2000);
        });
    }

    this.resetMsg = function () {
        $tmp_msgs = [];
    }

    this.addMsg = function (msg) {
        msg = msg.trim();
        if (msg != '') {
            $tmp_msgs.push('<STX>' + msg + '<ETX>C9<CR><LF>');
            $need_send_eot = true;
        }

    }

    this.getMsg = function () {
        if (!$tmp_msgs || $tmp_msgs.length == 0) {
            return '';
        }
        var firstItem = $tmp_msgs.slice(0, 1).shift();
        $tmp_msgs.shift();
        return firstItem;
    }

    this.send = function (msg) {
        try {
            msg = msg.replace(/(\n|\r)/g, '');
            if (msg == '') {
                msg = this.getMsg();
            }
            if (msg == '') {
                return false;
            }
            socket.send(JSON.stringify({ kind: "send_data", "data": msg }));
            $this.addTextView('Sent', msg);
        } catch (e) {
            alert('send fail: ' + msg);
        }

    }

    this.addTextView = function ($title, $msg) {
        var $textarea = $('#ws_receving');
        var $text = $textarea.val();
        if ($text == '') {
            $text = $text_wslog;
        }
        if ($text != '') {
            $text += "\n\n";
        }
        $text += $title + ': ' + $msg;
        $textarea.val($text);
        $text_wslog = $text;

        $textarea.scrollTop($textarea[0].scrollHeight);
    }

    this.cronloadlog = function () {
        clearInterval($intvlog);
        $this.load_log_test_server();
        $intvlog = setInterval($this.load_log_test_server, 3000);
    }

    this.load_log_test_server = function () {
        $.ajax({
            data: { trajax: 1, act: 'load_log_test_server', clear: $this.clearlogserver, port: $ws_port },
            dataType: 'json',
            success: function (rs) {
                $this.clearlogserver = 0;
                var $textarea = $('#log_server');
                $textarea.val(rs.content);
                if ($lastlogtime != rs.time) {
                    $textarea.scrollTop($textarea[0].scrollHeight);
                }
                $lastlogtime = rs.time;
            }
        });
    }

};

var testclient = new TestClient(8900);
var $sending = false
jQuery(function ($) {
    $('body').on('click', 'a.ws_send', function () {
        var $msg = $(this).data('msg');
        $('#text_send').val($msg);
        return false;
    }).on('click', 'a.send_msgbtn', function () {

        var $msg = $('#text_send').val();
        $msg = $msg.replace(/\r/g, "\n");
        $msg = $msg.replace(/\n\n/g, "\n");
        if ($msg != '') {
            $sending = true;
            $('#text_send').val('');

            if (testclient.getType() == 'MSH') {
                testclient.send('<VT>' + $msg + '<FS><CR>');
            }
            else if (testclient.getType() == 'H' && $msg.indexOf("\n") > 0) {
                var $msgs = $msg.split("\n");
                $num = 0;
                testclient.resetMsg();
                for (i in $msgs) {
                    var msg = $msgs[i].trim();
                    if (msg == '') {
                        continue;
                    }
                    $num++;
                    if ($num == 8) $num = 0;
                    testclient.addMsg($num + msg);

                }
                testclient.send('<ENQ>');
            } else {
                testclient.send($msg);
            }

        }
        $(this).trigger('sentmsg');
        return false;
    }).on('click', 'a.clear_logbtn', function () {
        testclient.clearlogserver = 1;
        testclient.load_log_test_server();
        $('#log_server').val('');
    })
})