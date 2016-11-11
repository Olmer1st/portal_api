<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Tree\Node as Node;
use \Tree\BookInfo as BookInfo;
use \Tree\GenreInfo as GenreInfo;

include $_SERVER['DOCUMENT_ROOT'] . '/v1/slim.app.php';
include $_SERVER['DOCUMENT_ROOT'] . '/v1/library/GenreInfo.php';
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
export interface GenreGroup {
    details: GenreInfo;
    genres: GenreInfo[];
}

export interface GenreInfo {
    gid: number;
    code: string;
    gdesc: string;
    edesc: string;
}

echo bin2hex($str)
echo pack("H*",bin2hex($str)) . "<br />";
 */
$app->get('/genres', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];

    $callbackForIds = function ($row) {
        return $row["gid"];
    };
    $createGenreTree = function ($row) use ($db, $callbackForIds) {
        $genreInfo = new GenreInfo(["gid" => $row["gid"],
            "code" => $row["code"],
            "gdesc" => $row["gdesc"],
            "edesc" => $row["edesc"]]);

        $genreIds = array_map($callbackForIds, iterator_to_array($db->lib_genre2group()->select("gid")->where("gidm", $row["gid"])));
        $genres = array_map(function ($genre) {
            $genreInfo = new GenreInfo(["gid" => $genre["gid"],
                "code" => $genre["code"],
                "gdesc" => $genre["gdesc"],
                "edesc" => $genre["edesc"]]);
            return $genreInfo;
        }, iterator_to_array($db->lib_genres()->select("gid,code,gdesc,edesc")->where("gid", $genreIds)));

        return [
            "details" => $genreInfo,
            "genres" => $genres
        ];
    };

    $groupIds = array_map($callbackForIds, iterator_to_array($db->lib_genre2group()->select("DISTINCT gidm as 'gid'")));
    $groups = array_map($createGenreTree, iterator_to_array($db->lib_genres()->select("gid,code,gdesc,edesc")->where("gid", $groupIds)));

    return $response->withJson($groups, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});
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
        if (isset($book["series"]) && strlen($book["series"]) > 0) {
            $serieId = md5($book["series"]); //bin2hex($book["series"]);
            $nodeSerie = new Node(["id" => $serieId,
                "title" => $book["series"],
                "type" => 3,
                "level" => 0]);
            if (!in_array($nodeSerie, $booksTree))
                array_push($booksTree, $nodeSerie);

            $bookInfo = new BookInfo($book);
            $nodeBook = new Node(["id" => $book["bid"],
                "title" => $book["title"],
                "type" => 1,
                "level" => 1,
                "parent" => $serieId,
                "bookInfo" => $bookInfo]);
            array_push($booksTree, $nodeBook);
        } else {
            $bookInfo = new BookInfo($book);
            $nodeBook = new Node(["id" => $book["bid"],
                "title" => $book["title"],
                "type" => 1,
                "level" => 0,
                "bookInfo" => $bookInfo->toArray()]);
            array_push($booksWithoutSerie, $nodeBook);
        }
    }

    $treeInfo = [
        'totalIds' => count($bookIds),
        'totalBooks' => $allBooks->count(),
        'maxLevel' => 1,
        'treeData' => array_merge($booksTree, $booksWithoutSerie)
    ];

    return $response->withJson($treeInfo, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->run();
