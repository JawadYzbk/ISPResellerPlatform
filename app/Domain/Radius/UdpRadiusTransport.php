<?php

namespace App\Domain\Radius;

use RuntimeException;

final class UdpRadiusTransport implements RadiusTransport
{
    public function send(string $host, int $port, string $packet): ?string
    {
        $errorCode = 0;
        $errorMessage = '';
        $socket = @stream_socket_client('udp://'.$host.':'.$port, $errorCode, $errorMessage, 2, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            throw new RuntimeException('RADIUS transport unavailable: '.$errorMessage);
        }

        stream_set_timeout($socket, 2);
        if (fwrite($socket, $packet) === false) {
            fclose($socket);
            throw new RuntimeException('RADIUS packet could not be sent.');
        }
        $response = fread($socket, 4096);
        fclose($socket);

        return $response === false ? null : $response;
    }
}
