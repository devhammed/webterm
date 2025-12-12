<?php

namespace Devhammed\Webterm;

use Exception;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use SplObjectStorage;

class TerminalServer implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface, TerminalContext> */
    protected SplObjectStorage $clients;

    protected array $env;

    private function __construct(array $env)
    {
        $this->env = $env;

        $this->clients = new SplObjectStorage;
    }

    public static function make(LoopInterface $loop, array $env = []): WsServer
    {
        $terminalServer = new static($env);

        $wsServer = new WsServer($terminalServer);

        $wsServer->enableKeepAlive($loop);

        $loop->addPeriodicTimer(0.01, [$terminalServer, 'tick']);

        return $wsServer;
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            ['script', '-q', '/dev/null', '-c', $this->env['SHELL'] ?? '/bin/bash'],
            $descriptors,
            $pipes,
            $this->env['HOME'] ?? null,
            $this->env ?? [],
        );

        if ( ! is_resource($process)) {
            $conn->close();

            return;
        }

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $this->clients->attach($conn, new TerminalContext(
            $process,
            $pipes,
        ));
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        if ( ! $this->clients->contains($from)) {
            return;
        }

        $context = $this->clients[$from];

        fwrite($context->pipes[0], $msg);
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if ( ! $this->clients->contains($conn)) {
            return;
        }

        $context = $this->clients[$conn];

        foreach ($context->pipes as $pipe) {
            fclose($pipe);
        }

        proc_terminate($context->process);

        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        $this->onClose($conn);

        $conn->close();
    }

    public function tick(): void
    {
        foreach ($this->clients as $conn) {
            $context = $this->clients[$conn];

            $output = stream_get_contents($context->pipes[1]);

            $error = stream_get_contents($context->pipes[2]);

            $data = $output . $error;

            if ($data !== '') {
                $conn->send($data);
           }
        }
    }
}
