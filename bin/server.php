<?php

use Devhammed\Webterm\FrontendServer;
use Ratchet\App;
use Devhammed\Webterm\TerminalServer;
use React\EventLoop\Loop;
use Symfony\Component\Routing\Route;

require __DIR__ . '/../vendor/autoload.php';

$env = getenv() ?: [];

$host = $env['HOST'] ?? 'localhost';

$port = $env['PORT'] ?? 8080;

$address = $env['ADDR'] ?? '127.0.0.1';

$loop = Loop::get();

$app = new App($host, $port, $address, $loop);

$app->routes->add('terminal', new Route(
    path: '/terminal',
    defaults: ['_controller' => TerminalServer::make($loop, $env)],
    methods: 'GET'
));

$app->routes->add('frontend', new Route(
    path: '{frontend}',
    defaults: ['_controller' => FrontendServer::make()],
    requirements: ['frontend' => '.*'],
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
));

echo "Server started.\nHTTP: http://{$host}:{$port}\nTerminal: ws://{$host}:{$port}/terminal\n";

$app->run();
