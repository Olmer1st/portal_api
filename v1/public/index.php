<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Administration\Users as Users;

include $_SERVER['DOCUMENT_ROOT'] . '/v1/slim.app.php';
include $_SERVER['DOCUMENT_ROOT'] . '/modules/users.php';


//test api
$app->get('/hello/{name}', function (Request $request, Response $response) {
    $name = $request->getAttribute('name');
    $data = array('name' => $name, 'age' => 40);
    $newResponse = $response->withJson($data);
    return $newResponse;
});




$app->get('/login/{email}/{password}', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $authService = $this->get('settings')['authService'];
    $email = $request->getAttribute('email');
    $password = $request->getAttribute('password');
    $data = Users::login($db, $authService, $email, $password);
    $newResponse = $response->withJson($data);
    return $newResponse;
});

$app->post('/authenticate', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $authService = $this->get('settings')['authService'];
    $body = $request->getParsedBody();
    $token = $body['token'];
    $data = $authService->authenticate($db, $token);
    $newResponse = $response->withJson($data);
    return $newResponse;
});

$app->run();
