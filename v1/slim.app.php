<?php
use \middleware\AuthenticationMiddleware;

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'] . '/middleware/authentication.php';
require $_SERVER['DOCUMENT_ROOT'] . '/middleware/connection.php';

$notOrm = connect();
$authMiddleWare = new AuthenticationMiddleware($notOrm);

$app = new \Slim\App([
    'settings' => [
        'determineRouteBeforeAppMiddleware' => false,
        'displayErrorDetails' => true,
        'addContentLengthHeader' => false,
        'notOrm' =>  $notOrm,
        'authService' => $authMiddleWare

    ]
]);

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Origin, Authorization, X-Access-Token, Accept, Accept-Encoding')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->add($authMiddleWare);
