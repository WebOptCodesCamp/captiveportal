<?php
// ----------------------------------------------------------------
// --- MikroTik RouterOS API Class ---
// ----------------------------------------------------------------

class RouterosAPI {
    public $debug = false;
    public $error_no = 0;
    public $error = "";
    public $timeout = 3;
    
    private $socket;
    private $connected = false;
    
    /**
     * Connect to RouterOS
     */
    public function connect($ip, $login, $password, $port = 8728) {
        for ($ATTEMPT = 1; $ATTEMPT <= 2; $ATTEMPT++) {
            $this->connected = false;
            $this->debug && print("Connection attempt #$ATTEMPT to $ip:$port...");
            
            $this->socket = @fsockopen($ip, $port, $this->error_no, $this->error, $this->timeout);
            
            if ($this->socket) {
                socket_set_timeout($this->socket, $this->timeout);
                $this->write('/login');
                $this->write('=name=' . $login);
                $this->write('=password=' . $password);
                $this->write('', false);
                
                $RESPONSE = $this->read(false);
                
                if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
                    $this->connected = true;
                    $this->debug && print("Connected successfully!\n");
                    break;
                } else {
                    $this->debug && print("Login failed!\n");
                }
            } else {
                $this->debug && print("Connection failed!\n");
            }
        }
        
        return $this->connected;
    }
    
    /**
     * Disconnect from RouterOS
     */
    public function disconnect() {
        if ($this->connected) {
            @fclose($this->socket);
            $this->connected = false;
        }
    }
    
    /**
     * Parse response from RouterOS
     */
    public function parseResponse($response) {
        if (is_array($response)) {
            $PARSED = array();
            $CURRENT = null;
            $singlevalue = null;
            
            foreach ($response as $x) {
                if (in_array($x, array('!fatal', '!re', '!trap'))) {
                    if ($x == '!re') {
                        $CURRENT =& $PARSED[];
                    }
                } elseif (substr($x, 0, 1) == '=') {
                    $MATCHES = array();
                    if (preg_match_all('/^=([^=]+)=(.*)/', $x, $MATCHES)) {
                        if ($CURRENT === null) {
                            $CURRENT =& $PARSED[];
                        }
                        $CURRENT[$MATCHES[1][0]] = $MATCHES[2][0];
                    }
                }
            }
            
            return $PARSED;
        } else {
            return array();
        }
    }
    
    /**
     * Read response from RouterOS
     */
    public function read($parse = true) {
        $RESPONSE = array();
        $receiveddone = false;
        
        while (true) {
            $BYTE = ord(fread($this->socket, 1));
            $LENGTH = 0;
            
            if ($BYTE & 0x80) {
                if (($BYTE & 0xC0) == 0x80) {
                    $LENGTH = !($BYTE & 0x20) ? fread($this->socket, 1) : fread($this->socket, 2);
                    $LENGTH = ord($LENGTH);
                } elseif (($BYTE & 0xE0) == 0xC0) {
                    $LENGTH = fread($this->socket, 2);
                    $LENGTH = unpack('n', $LENGTH)[1];
                } elseif (($BYTE & 0xF0) == 0xE0) {
                    $LENGTH = fread($this->socket, 3);
                    $LENGTH = unpack('N', "\x00" . $LENGTH)[1];
                } elseif (($BYTE & 0xF8) == 0xF0) {
                    $LENGTH = fread($this->socket, 4);
                    $LENGTH = unpack('N', $LENGTH)[1];
                }
            } else {
                $LENGTH = $BYTE;
            }
            
            if ($LENGTH > 0) {
                $_ = "";
                $retlen = 0;
                while ($retlen < $LENGTH) {
                    $toread = $LENGTH - $retlen;
                    $_ .= fread($this->socket, $toread);
                    $retlen = strlen($_);
                }
                $RESPONSE[] = $_;
                $this->debug && print("<<< " . $_ . "\n");
                
                if ($_ == '!done') {
                    $receiveddone = true;
                }
            }
            
            if ($receiveddone && $LENGTH == 0) {
                break;
            }
        }
        
        if ($parse) {
            $RESPONSE = $this->parseResponse($RESPONSE);
        }
        
        return $RESPONSE;
    }
    
    /**
     * Write command to RouterOS
     */
    public function write($str, $param = true) {
        if ($param) {
            $this->debug && print(">>> " . $str . "\n");
        }
        
        $len = strlen($str);
        
        if ($len < 0x80) {
            $len = chr($len);
        } elseif ($len < 0x4000) {
            $len = chr(0x80 | ($len >> 8)) . chr($len & 0xFF);
        } elseif ($len < 0x200000) {
            $len = chr(0xC0 | ($len >> 16)) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        } elseif ($len < 0x10000000) {
            $len = chr(0xE0 | ($len >> 24)) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        } else {
            $len = chr(0xF0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        }
        
        return fwrite($this->socket, $len . $str);
    }
    
    /**
     * Write command and read response
     */
    public function comm($com, $arr = array()) {
        $count = count($arr);
        $this->write($com, true);
        
        foreach ($arr as $k => $v) {
            switch (substr($k, 0, 1)) {
                case "?":
                    $this->write("?" . $k . "=" . $v, true);
                    break;
                case "~":
                    $this->write("~" . $k . "=" . $v, true);
                    break;
                default:
                    $this->write("=" . $k . "=" . $v, true);
                    break;
            }
        }
        
        $this->write("", false);
        
        return $this->read();
    }
}