<?php

declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    $routes->connect(
        '/api/ws-message',
        ['controller' => 'Api', 'action' => 'wsMessage']
    )->setMethods(['POST']);

    $routes->connect(
        '/auth/login',
        ['controller' => 'Auth', 'action' => 'login']
    )->setMethods(['POST']);

    $routes->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);

    $routes->connect(
        '/devices/dashboard/:deviceId',
        ['controller' => 'Devices', 'action' => 'dashboard']
    )->setPass(['deviceId']);

    $routes->connect(
        '/devices/send-config/:deviceId',
        ['controller' => 'Devices', 'action' => 'sendConfig']
    )->setPass(['deviceId'])->setMethods(['POST']);

    $routes->connect(
        '/devices/telemetry/:deviceId',
        ['controller' => 'Devices', 'action' => 'telemetry']
    )->setPass(['deviceId']);

    $routes->connect(
        '/devices/plot/:deviceId',
        ['controller' => 'Devices', 'action' => 'plot']
    )->setPass(['deviceId']);

    $routes->fallbacks(DashedRoute::class);
};