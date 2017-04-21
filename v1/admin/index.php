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

$app->get('/modules', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $modules = users::getModules($db);
    return $response->withJson($modules, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->post('/modules', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $body = $request->getParsedBody();
    $affected = null;
    if(isset($body["module"])){
        $module = $body["module"];
        if(isset($module["mid"])){
            $affected = users::updateModule($db,$module);
        }else{
            $affected = users::insertModule($db,$module);
        }
    }

    return $response->withJson($affected, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->delete('/modules/{mid}', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $mid = $request->getAttribute('mid');
    $affected = users::deleteModule($db, $mid);
    return $response->withJson($affected, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});


$app->post('/users', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $body = $request->getParsedBody();
    $affected = null;
    if(isset($body["user"])){
        $user = $body["user"];
        if(isset($user["uid"])){
            $affected = users::updateUser($db,$user);
        }else{
            $affected = users::insertUser($db,$user);
        }
    }

    return $response->withJson($affected, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->put('/users/{uid}/{lock}', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $uid = $request->getAttribute('uid');
    $lock = $request->getAttribute('lock');
    $affected = users::lockUser($db,$uid, $lock);

    return $response->withJson($affected, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->delete('/users/{uid}', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $uid = $request->getAttribute('uid');
    $affected = users::deleteUser($db, $uid);
    return $response->withJson($affected, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->run();
