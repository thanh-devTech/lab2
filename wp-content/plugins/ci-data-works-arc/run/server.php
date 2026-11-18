<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

set_time_limit(0);


class Server
{

    private $sockServer;
    private $port;
    private $address;
    private $thread_class = '';
    public static $pid_file = 'pid.txt';
    public static $debug = false;

    public function __construct($port, $address = '0.0.0.0')
    {
        $this->port = $port;
        $this->address = $address;
    }

    public function create()
    {
        printf("creating socket");

        $this->sockServer = stream_socket_server("tcp://{$this->address}:{$this->port}", $errno, $errstr);
        if (!$this->sockServer) {
            return false;
        }
        return true;
    }

    public function setThreadClass($classname)
    {
        $this->thread_class = $classname;
    }

    public function setDebug($debug)
    {
        self::$debug = $debug;
    }


    public function listen()
    {
        if (!$this->sockServer) return false;

        $is_parent = true;

        $this->add_pid(posix_getpid());

        do {
            try {

                echo 'waiting connect' . "\n";
                if (($msgsock = stream_socket_accept($this->sockServer)) === false) {
                    echo 'error:stream_socket_accept ';
                    //break;
                }

                if (empty($this->thread_class)) {
                    $pid = pcntl_fork();
                    if ($pid == -1) {
                        die('could not fork');
                    } else if ($pid) {
                        $this->add_pid($pid);
                        //is parent
                        continue;
                    }

                    $is_parent = false;
                    $this->rev_data($msgsock);
                    exit;
                } else {
                    $client = new $this->thread_class($msgsock);
                    $client->start();
                }
            } catch (Exception $e) {
                var_dump($e);
            }
        } while ($is_parent);

        @socket_close($this->sockServer);
        echo 'Closed server' . "\n";
    }

    public function add_pid($pid)
    {
        try {
            $f = fopen(self::$pid_file, 'a+');
            fwrite($f, $pid . "\n");
            fclose($f);
        } catch (Exception $e) {
        }
    }

    public function rev_data($msgsock)
    {
        do {
            try {

                if (false === ($buf = @socket_read($msgsock, 2048, PHP_NORMAL_READ))) {
                    echo "socket_read() failed: reason: " . socket_strerror(socket_last_error($msgsock)) . "\n";
                    break;
                }
                if (!$buf = trim($buf)) {
                    continue;
                }
                if (strpos($buf, 'quit') !== false) {
                    break;
                }

                $talkback = 'Msg:' . $buf . "\n";
                @socket_write($msgsock, $talkback, strlen($talkback));
            } catch (Exception $e) {
                var_dump($e);
            }
        } while (true);


        @socket_shutdown($msgsock);
        @socket_close($msgsock);
    }
}

$server = new Server(8684);

if ($server->create()) {
    $server->listen();
    echo date('Y-m-d H:i:s') . ' - Stopped Server!  ';
} else {
    echo 'Cant create Socket' . "\n";
}
