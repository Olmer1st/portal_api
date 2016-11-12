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

        $genreIds = array_map($callbackForIds, iterator_to_array($db->lib_genre2group()
            ->select("gid")
            ->where("gidm", $row["gid"])));
        $genres = array_map(function ($genre) {
            $genreInfo = new GenreInfo(["gid" => $genre["gid"],
                "code" => $genre["code"],
                "gdesc" => $genre["gdesc"],
                "edesc" => $genre["edesc"]]);
            return $genreInfo;
        }, iterator_to_array($db->lib_genres()
            ->select("gid,code,gdesc,edesc")
            ->where("gid", $genreIds)));

        return [
            "details" => $genreInfo,
            "genres" => $genres
        ];
    };

    $groupIds = array_map($callbackForIds, iterator_to_array($db->lib_genre2group()
        ->select("DISTINCT gidm as 'gid'")));
    $groups = array_map($createGenreTree, iterator_to_array($db->lib_genres()
        ->select("gid,code,gdesc,edesc")
        ->where("gid", $groupIds)));

    return $response->withJson($groups, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});
$app->get('/books/genre/{gid}/{lang}', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $gid = $request->getAttribute('gid');
    $lang = $request->getAttribute('lang');
    $start = microtime(true);
    $callbackIds = function ($row) {
        return $row["bid"];
    };
    $bookIds = array_map($callbackIds, iterator_to_array($db->lib_genre2book()
        ->select("gid, bid")
        ->where("gid", $gid)));
    $authorRefs = [];
    foreach ($db->lib_author2book()->select("aid, bid")->where("bid", $bookIds) as $authorRef) {
        if (!array_key_exists($authorRef["aid"], $authorRefs)) {
            $authorRefs[$authorRef["aid"]] = [$authorRef["bid"]];
        } else {
            array_push($authorRefs[$authorRef["aid"]], $authorRef["bid"]);
        }

    }
    $booksTree = [];
    $booksCount = 0;
    foreach ($db->lib_authors()->select("aid, fullname")->where("aid", array_keys($authorRefs))->order("fullname ASC") as $author){
        $nodeAuthor = new Node(["id" => $author["aid"],
            "title" => $author["fullname"],
            "type" => 2,
            "level" => 0]);
        array_push($booksTree, $nodeAuthor);
        foreach ($db->lib_books()
                     ->select("bid, author, genre, title, series, serno, file, size,  ext, date, lang, path")
                     ->where("bid", $authorRefs[$author["aid"]])
                     ->where("del", null)
                     ->where("lang", $lang)
                     ->order("series ASC, serno ASC, title ASC") as $book){
            $booksCount ++;
            if (isset($book["series"]) && strlen($book["series"]) > 0) {
                $serieId = md5($book["series"]);
                $nodeSerie = new Node(["id" => $serieId,
                    "title" => $book["series"],
                    "type" => 3,
                    "level" => 1,
                    "parent" => $author["aid"]]);
                if (!in_array($nodeSerie, $booksTree))
                    array_push($booksTree, $nodeSerie);

                $bookInfo = new BookInfo($book);
                $nodeBook = new Node(["id" => $book["bid"],
                    "title" => $book["title"],
                    "type" => 1,
                    "level" => 2,
                    "parent" => $serieId,
                    "bookInfo" => $bookInfo]);
                array_push($booksTree, $nodeBook);
            } else {
                $bookInfo = new BookInfo($book);
                $nodeBook = new Node(["id" => $book["bid"],
                    "title" => $book["title"],
                    "type" => 1,
                    "level" => 1,
                    "bookInfo" => $bookInfo]);
                array_push($booksTree, $nodeBook);
            }
        }
    }


    $treeInfo = [
        'microtime' => microtime(true) - $start,
        'totalIds' => count($bookIds),
        'totalBooks' => $booksCount,
        'maxLevel' => 2,
        'treeData' => $booksTree//array_merge(, $booksWithoutSerie)
    ];

    return $response->withJson($treeInfo, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});
$app->get('/books/author/{aid}/{lang}', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $aid = $request->getAttribute('aid');
    $lang = $request->getAttribute('lang');
    $callbackIds = function ($row) {
        return $row["bid"];
    };
    $booksTree = [];
    $booksWithoutSerie = [];
    $createTreeInfo = function ($book) use (&$booksTree, &$booksWithoutSerie) {
        if (isset($book["series"]) && strlen($book["series"]) > 0) {
            $serieId = md5($book["series"]);
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
        return $book["bid"];
    };
    $bookIds = array_map($callbackIds, iterator_to_array($db->lib_author2book()
        ->select("aid, bid")
        ->where("aid", $aid)));
    $allBooks = array_map($createTreeInfo, iterator_to_array($db->lib_books()
        ->select("bid, author, genre, title, series, serno, file, size,  ext, date, lang, path")
        ->where("bid", $bookIds)
        ->where("del", null)
        ->where("lang", $lang)
        ->order("series ASC, serno ASC, title ASC")));

    $treeInfo = [
        'totalIds' => count($bookIds),
        'totalBooks' => count($allBooks),
        'maxLevel' => 1,
        'treeData' => array_merge($booksTree, $booksWithoutSerie)
    ];

    return $response->withJson($treeInfo, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->run();
