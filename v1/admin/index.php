<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use modules\Administration\Users as Users;

include $_SERVER['DOCUMENT_ROOT'] . '/v1/slim.app.php';
include $_SERVER['DOCUMENT_ROOT'] . '/modules/users.php';


//test api
$app->get('/hello/{name}', function (Request $request, Response $response) {
    $name = $request->getAttribute('name');
    $data = array('name' => $name, 'age' => 40);
    $newResponse = $response->withJson($data);
    return $newResponse;
});


$app->get('/createuser/{email}/{password}', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
//    $authService = $this->get('settings')['authService'];
    $email = $request->getAttribute('email');
    $password = $request->getAttribute('password');
    $data = Users::createNewUser($db, $email, "Olmer", $password, "admin");
    $newResponse = $response->withJson($data);
    return $newResponse;
});

$app->get('/users', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $users = users::getUsers($db);
    return $response->withJson($users, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->run();
