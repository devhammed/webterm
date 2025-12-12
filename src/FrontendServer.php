<?php

namespace Devhammed\Webterm;

use Exception;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use UnexpectedValueException;

class FrontendServer implements HttpServerInterface
{
    private function __construct()
    {}

    public static function make(): static
    {
        return new static();
    }

    public function onOpen(ConnectionInterface $conn, ?RequestInterface $request = null): void
    {
        if ($request === null) {
            throw new UnexpectedValueException('Request can not be null.');
        }

        if ($request->getMethod() === 'GET' && $request->getUri()->getPath() === '/') {
            $response = new Response(
                200,
                ['Content-Type' => 'text/html'],
                file_get_contents(__DIR__ . '/../public/index.html'),
            );

            $conn->send(Message::toString($response));

            $conn->close();

            return;
        }

        $response = new Response(404, [], 'Not Found');

        $conn->send(Message::toString($response));

        $conn->close();
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        //
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $conn->close();
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        $response = new Response(500, [], 'Internal Server Error');

        $conn->send(Message::toString($response));

        $conn->close();
    }
}
