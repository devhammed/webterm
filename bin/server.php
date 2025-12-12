<?php

use Devhammed\Webterm\FrontendServer;
use Ratchet\App;
use Devhammed\Webterm\TerminalServer;
use Symfony\Component\Routing\Route;

require __DIR__ . '/../vendor/autoload.php';

$env = getenv() ?: [];

$host = $env['HOST'] ?? '127.0.0.1';

$port = $env['PORT'] ?? 8080;

$app = new App($host, $port);

$app->routes->add('ws', new Route(
    path: '/terminal',
    defaults: ['_controller' => TerminalServer::make($env)],
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
