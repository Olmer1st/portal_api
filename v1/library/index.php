<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include $_SERVER['DOCUMENT_ROOT'] . '/v1/slim.app.php';

//test api
$app->get('/hello/{name}', function (Request $request, Response $response) {
    $name = $request->getAttribute('name');
    $data = array('name' => $name, 'age' => 40);
    $newResponse = $response->withJson($data);
    return $newResponse;
});
$app->get('/authors/[{limit}]', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $limit = (null !== $request->getAttribute('limit')) ? $request->getAttribute('limit') : 10;
    $authors = $db->lib_authors()->select("aid, fullname")->limit($limit);
    return $response->withJson($authors, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});
$app->get('/authors/search/[{q}]', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $q = $request->getAttribute('q');
    $authors = [];
    if (isset($q))
        $authors = $db->lib_authors()->select("aid, fullname")->where("fullname LIKE ?", "%$q%")->order("fullname");
    return $response->withJson($authors, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});
$app->get('/languages', function (Request $request, Response $response) {


    $db = $this->get('settings')['notOrm'];
    $languages = [];
    foreach ($db->lib_books()->select("DISTINCT lang")->order("lang") as $lang) {
        array_push($languages, $lang["lang"]);
    }
    return $response->withJson($languages, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});
$app->get('/books/author/{aid}', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $aid = $request->getAttribute('aid');
    $booksRef = $db->lib_author2book()->select("aid, bid")->where("aid", $aid)->fetchPairs("bid");
    $bookIds = [];
    foreach ($booksRef as $refBook) {
        array_push($bookIds, $refBook["bid"]);
    }
    $res = $db->lib_books()
        ->select("bid, author, genre, title, series, serno, file, size,  ext, date, lang, path")
        ->where("bid", $bookIds)
        ->where("del", null);
    $books = [
        'totalIds' => count($bookIds),
        'totalBooks' => $res->count(),
        'books' => $res
    ];

    return $response->withJson($books, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->run();
