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

    protected array $command;

    protected string $shell;

    protected string $home;

    protected LoopInterface $loop;

    private function __construct(LoopInterface $loop, array $env = [])
    {
        $this->env = $env;

        $this->loop = $loop;

        $this->clients = new SplObjectStorage;

        $this->home = $this->env['HOME'] ?? '/';

        $this->shell = $this->env['SHELL'] ?? '/bin/bash';

        if (PHP_OS_FAMILY === 'Linux') {
            $this->command = ['script', '-q', '/dev/null', '-c', $this->shell];
        } elseif (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'BSD' || PHP_OS_FAMILY === 'Solaris') {
            $this->command = ['script', '-q', '/dev/null', $this->shell];
        } else {
            throw new Exception('Unsupported OS.');
        }
    }

    public static function make(LoopInterface $loop, array $env = []): WsServer
    {
        $wsServer = new WsServer(new static($loop, $env));

        $wsServer->enableKeepAlive($loop);

        return $wsServer;
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($this->command, $descriptors, $pipes, $this->home, $this->env);

        if ( ! is_resource($process)) {
            $conn->close();

            return;
        }

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $timer = $this->loop->addPeriodicTimer(0.000001, function () use ($conn, $pipes) {
            $output = stream_get_contents($pipes[1]);

            $error = stream_get_contents($pipes[2]);

            $data = $output . $error;

            if ($data !== '') {
                $conn->send($data);
            }
        });

        $this->clients->attach($conn, new TerminalContext(
            $process,
            $pipes,
            $timer,
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

        $this->loop->cancelTimer($context->timer);

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
}
