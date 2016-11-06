<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Tree\Node as Node;
use \Tree\BookInfo as BookInfo;

include $_SERVER['DOCUMENT_ROOT'] . '/v1/slim.app.php';
include $_SERVER['DOCUMENT_ROOT'] . '/v1/library/BookInfo.php';
include $_SERVER['DOCUMENT_ROOT'] . '/v1/library/Node.php';
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
/*
 export interface Node {
    id:number;
    title: string;
    type: NodeType;
    level: number;
    bookInfo:BookInfo;
};

export enum NodeType {
    None = 0,
    Book = 1,
    Author = 2,
    Serie = 3
};

export interface BookInfo {
    title: string;
    size:number;
    serno:number;
    lang:string;
    del:boolean;
    path:string;
    file:number;
    date:string;
    ext:string;
    genre:string;
};
 */
$app->get('/books/author/{aid}/{lang}', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $aid = $request->getAttribute('aid');
    $lang = $request->getAttribute('lang');
    $booksRef = $db->lib_author2book()->select("aid, bid")->where("aid", $aid)->fetchPairs("bid");
    $bookIds = [];
    foreach ($booksRef as $refBook) {
        array_push($bookIds, $refBook["bid"]);
    }
    $allBooks = $db->lib_books()
        ->select("bid, author, genre, title, series, serno, file, size,  ext, date, lang, path")
        ->where("bid", $bookIds)
        ->where("del", null)
        ->where("lang", $lang)
        ->order("series ASC, serno ASC, title ASC");
    $booksTree = [];
    $booksWithoutSerie = [];
    foreach ($allBooks as $book) {
        if (isset($book["series"]) && strlen($book["series"])>0) {
            $nodeSerie = new Node(["id" => -1,
                "title" => $book["series"],
                "type" => 3,
                "level" => 0]);
            if(!in_array($nodeSerie->toArray(), $booksTree))
                array_push($booksTree, $nodeSerie->toArray());

            $bookInfo = new BookInfo($book);
            $nodeBook = new Node(["id" => $book["bid"],
                "title" => $book["title"],
                "type" => 1,
                "level" => 1,
                "bookInfo" => $bookInfo->toArray()]);
            array_push($booksTree, $nodeBook->toArray());
        }else{
            $bookInfo = new BookInfo($book);
            $nodeBook = new Node(["id" => $book["bid"],
                "title" => $book["title"],
                "type" => 1,
                "level" => 0,
                "bookInfo" => $bookInfo->toArray()]);
            array_push($booksWithoutSerie, $nodeBook->toArray());
        }
    }

    $treeInfo = [
        'totalIds' => count($bookIds),
        'totalBooks' => $allBooks->count(),
        'treeData' => array_merge($booksTree, $booksWithoutSerie)
    ];

    return $response->withJson($treeInfo, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->run();
