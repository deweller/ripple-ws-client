<?php

namespace RippleWSClient;

use RippleWSClient\Error\ConnectionException;
use \Exception;

/*
* Synchronous Connection to a ripple websocket server
*/
class Connection
{

    const RFC6455_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';


    private $host;
    private $port;
    private $secure;

    private $timeout = 10;

    private $socket_connection;
    private $connection_key;

    public function __construct($host='s1.ripple.com', $port=443, $secure=true) {
        $this->host = $host;
        $this->port = $port;
        $this->secure = $secure;
    }

    public function connect() {
        // this will ensure that the socket connects
        $this->getConnectionSocket();
    }

    public function close() {
        fclose($this->socket_connection);
    }

    public function send($content)
    {
        $socket = $this->getConnectionSocket();
        if (!$socket OR !is_resource($socket)) { throw new Exception("Failed to connect", 1); }

        $OPCODE_TEXT_FRAME = 0x1;
        $frame = $this->buildFrame($content, $OPCODE_TEXT_FRAME);

        fwrite($socket, $frame);

        $response = $this->readFrame($socket);

        // probably closed
        if (!isset($response['message'])) { return null; }

        return $response['message'];
    }


    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////

    protected function resetConnection() {
        $this->socket_connection = null;
        $this->connection_key    = null;
    }

    protected function getConnectionSocket() {
        if ($this->socket_connection !== null) { return $this->socket_connection; }

        $socket = $this->establishConnectionSocket();

        // initial handshake
        $this->initiateHandshake($socket);

        $this->socket_connection = $socket;
        return $this->socket_connection;
    }

    protected function establishConnectionSocket() {
        $ip = gethostbyname($this->host);
        $protocol = ($this->secure ? 'tls' : 'tcp');
        $url = "{$protocol}://{$ip}:{$this->port}";

        $socket = stream_socket_client($url, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, stream_context_create([]));
        if ($errno) { throw new ConnectionException($errstr, $errno); }
        if (!$socket OR !is_resource($socket)) { throw new ConnectionException("Failed to connect", 1); }

        // make sure it is connected
        $connected_name = stream_socket_get_name($socket, true);
        if ($connected_name === false) { throw new ConnectionException("Connection refused", 1); }

        // set to blocking
        stream_set_blocking($socket, 1);

        return $socket;
    }


    protected function initiateHandshake($socket) {
        $this->connection_key = substr(base64_encode(sha1(uniqid('',true))), 0, 21) . 'A' . '==';
        $expected = base64_encode(sha1($this->connection_key . self::RFC6455_GUID, true));

        $CRLF = "\r\n";
        $end_point='/';
        $request =
                    'GET ' . $end_point . ' HTTP/1.1' . $CRLF .
                    'Host: ' . $this->host . $CRLF .
                    'User-Agent: Hoa' . $CRLF .
                    'Upgrade: WebSocket' . $CRLF .
                    'Connection: Upgrade' . $CRLF .
                    'Pragma: no-cache' . $CRLF .
                    'Cache-Control: no-cache' . $CRLF .
                    'Sec-WebSocket-Key: ' . $this->connection_key . $CRLF .
                    'Sec-WebSocket-Version: 13' . $CRLF . $CRLF;
        fwrite($socket, $request);


        // read response
        $buffer = fread($socket, 2048);
        $parsed_data = $this->parse($buffer);

        // check the result
        $response_headers = $parsed_data['headers'];
        if(   '101' !== $parsed_data['status']
           || 'websocket' !== strtolower($response_headers['upgrade'])
           ||   'upgrade' !== strtolower($response_headers['connection'])
           ||   $expected !== $response_headers['sec-websocket-accept'])
            throw new ConnectionException(
                sprintf(
                'Handshake has failed, the server did not return a valid ' .
                'response.' . "\n\n" .
                'Client:' . "\n" . '    %s' . "\n" .
                'Server:' . "\n" . '    %s', 
                    str_replace("\n", "\n" . '    ', $request),
                    str_replace("\n", "\n" . '    ', $buffer)
                ), 1);
    }


    protected function parse ( $packet ) {
        $response = [];

        $headers     = explode("\r\n", $packet);
        $status      = array_shift($headers);
        $response['_body'] = null;

        foreach($headers as $i => $header)
            if('' == trim($header)) {

                unset($headers[$i]);
                $response['_body'] = trim(
                    implode("\r\n", array_splice($headers, $i))
                );
                break;
            }

        if(0 === preg_match('#^HTTP/(1\.(?:0|1))\s+(\d{3})#i', $status, $matches)) {
            throw new Exception('HTTP status is not well-formed: '.$status.'', 0);
        }

        $response['headers'] = $this->parseHeaders($headers);
        $response['_httpVersion'] = (float) $matches[1];
        $response['status']     = $matches[2];
        return $response;
    }

    protected function parseHeaders ( Array $headers ) {
        unset($out_headers);
        $out_headers = [];

        foreach($headers as $header) {
            list($name, $value)                = explode(':', $header, 2);
            $out_headers[strtolower($name)] = trim($value);
        }

        return $out_headers;
    }


    protected function buildFrame ( $message,
                                 $opcode,
                                 $end    = true ) {

        $fin    = true === $end ? 0x1 : 0x0;
        $rsv1   = 0x0;
        $rsv2   = 0x0;
        $rsv3   = 0x0;
        $mask   = 0x1;
        $length = strlen($message);
        $out    = chr(
            ($fin  << 7)
          | ($rsv1 << 6)
          | ($rsv2 << 5)
          | ($rsv3 << 4)
          | $opcode
        );

        if(0xffff < $length)
            $out .= chr(0x7f) . pack('NN', 0, $length);
        elseif(0x7d < $length)
            $out .= chr(0x7e) . pack('n', $length);
        else
            $out .= chr($length);

        $out .= $message;
        return $out;
    }

    protected function readFrame ($socket) {
        if (!$socket OR !is_resource($socket)) { throw new Exception("Connection closed", 1); }
        $OPCODE_CONNECTION_CLOSE   = 0x8;

        $out  = array();
        $read = fread($socket, 1);

        if(empty($read)) {
            $out['opcode'] = $OPCODE_CONNECTION_CLOSE;
            return $out;
        }

        $handle        = ord($read);
        $out['fin']    = ($handle >> 7) & 0x1;
        $out['rsv1']   = ($handle >> 6) & 0x1;
        $out['rsv2']   = ($handle >> 5) & 0x1;
        $out['rsv3']   = ($handle >> 4) & 0x1;
        $out['opcode'] =  $handle       & 0xf;

        $handle        = ord(fread($socket, 1));
        $out['mask']   = ($handle >> 7) & 0x1;
        $out['length'] =  $handle       & 0x7f;
        $length        = &$out['length'];

        if(0x0 !== $out['rsv1'] || 0x0 !== $out['rsv2'] || 0x0 !== $out['rsv3']) {
            // protocol error
            fclose($socket);
            return false;
        }

        if(0 === $length) {
            $out['message'] = '';
            return $out;
        }
        elseif(0x7e === $length) {

            $handle = unpack('nl', fread($socket, 2));
            $length = $handle['l'];
        }
        elseif(0x7f === $length) {

            $handle = unpack('N*l', fread($socket, 8));
            $length = $handle['l2'];

            if($length > 0x7fffffffffffffff)
                throw new \Hoa\Websocket\Exception(
                    'Message is too long.', 1);
        }

        if(0x0 === $out['mask']) {

            $out['message'] = fread($socket, $length);

            return $out;
        }

        $maskN = array_map('ord', str_split(fread($socket, 4)));
        $maskC = 0;

        $buffer       = 0;
        $bufferLength = 3000;
        $message      = null;

        for($i = 0; $i < $length; $i += $bufferLength) {

            $buffer = min($bufferLength, $length - $i);
            $handle = fread($socket, $buffer);

            for($j = 0, $_length = strlen($handle); $j < $_length; ++$j) {

                $handle[$j] = chr(ord($handle[$j]) ^ $maskN[$maskC]);
                $maskC      = ($maskC + 1) % 4;
            }

            $message .= $handle;
        }

        $out['message'] = $message;

        return $out;
    }

}
