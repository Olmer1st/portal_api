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
function createTreeInfo($book, &$booksTree, &$booksWithoutSerie)
{
    if (isset($book["series"]) && strlen($book["series"]) > 0) {
        $serieId = md5($book["series"]);
        $nodeSerie = null;
        $nodes = array_filter($booksTree, function ($row) use ($serieId) {
            return $row->data["id"] === $serieId;
        });
        if ($nodes && count($nodes) > 0) {
            $nodeSerie = current($nodes);
        } else {
            $nodeSerie = new Node(["id" => $serieId,
                "title" => $book["series"],
                "type" => 3]);
            $nodeSerie->children = [];
            $booksTree[] = $nodeSerie;
        }


        $bookInfo = new BookInfo($book);
        $nodeBook = new Node(["id" => $book["bid"],
            "title" => $book["title"],
            "type" => 1,
            "bookInfo" => $bookInfo]);
        $nodeSerie->children[] = $nodeBook;
    } else {
        $bookInfo = new BookInfo($book);
        $nodeBook = new Node(["id" => $book["bid"],
            "title" => $book["title"],
            "type" => 1,
            "level" => 0,
            "bookInfo" => $bookInfo]);
        $booksWithoutSerie[] = $nodeBook;
    }
}

function addNodesToArray($nodes, &$children)
{
    foreach ($nodes as $node) {
        $children[] = $node;
    }
}

$app->get('/series/all/{page}/{lang}[/{recordsnumber}]', function (Request $request, Response $response) {
    $start = microtime(true);
    $db = $this->get('settings')['notOrm'];
    $page = $request->getAttribute('page');
    $lang = $request->getAttribute('lang');
    $num_rec_per_page = $request->getAttribute('recordsnumber');
    $num_rec_per_page = (isset($num_rec_per_page)) ? $num_rec_per_page : 20;
    $start_from = ($page - 1) * $num_rec_per_page;
    $callbackNames = function ($row)  {
        return $row["series"];
    };

    $serieNames = array_map($callbackNames, iterator_to_array($db->lib_books()
        ->select("DISTINCT series")
        ->where("del", null)
        ->where("series <> '' and not series is null")
        ->where("lang", $lang)));


    $series = $db->lib_series()
        ->where("serie_name", $serieNames)
        ->order("serie_name ASC")
        ->limit($num_rec_per_page, $start_from);

    $dataInfo = [
        "totalItems" => count($serieNames),
        "series" => $series
    ];


    return $response->withJson($dataInfo, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->get('/series/search/{lang}[/{q}]', function (Request $request, Response $response) {
    $start = microtime(true);
    $db = $this->get('settings')['notOrm'];
    $q = $request->getAttribute('q');
    $lang = $request->getAttribute('lang');
    $callbackNames = function ($row)  {
        return $row["series"];
    };
    $series = [];
    if(isset($q)){
        $serieNames = array_map($callbackNames, iterator_to_array($db->lib_books()
            ->select("DISTINCT series")
            ->where("del", null)
            ->where("series <> '' and not series is null")
            ->where("series like '%$q%'")
            ->where("lang", $lang)));

        $series = $db->lib_series()
            ->where("serie_name", $serieNames)
            ->order("serie_name ASC");
    }

    return $response->withJson($series, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});
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
$app->get('/books/genrecode/{code}/{lang}', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $code = $request->getAttribute('code');
    $lang = $request->getAttribute('lang');
    $start = microtime(true);
    $booksCount = 0;
    $authorsData = [];
    $addBooks = function ($book, $author) use (&$authorsData) {
        if (!array_key_exists($author, $authorsData)) {
            $authorsData[$author] = ["seq" => [], "not_seq" => []];
        }
        createTreeInfo($book, $authorsData[$author]["seq"], $authorsData[$author]["not_seq"]);
    };

    foreach ($db->lib_books()
                 ->select("bid, author, genre, title, series, serno, file, size,  ext, date, lang, path")
                 ->where("genre LIKE ?", "%$code%")
                 ->where("del", null)
                 ->where("lang", $lang)
                 ->order("author ASC, series ASC, serno ASC, title ASC") as $book) {
        $booksCount++;
        $authors = explode(":", $book["author"]);
        if (count($authors) <= 2) {
            foreach ($authors as $authorName) {
                if (strlen(trim($authorName)) > 0) {
                    $fullname = trim(str_replace(",", " ", $authorName));
                    $addBooks($book, $fullname);
                }
            }
        }

    }
    $treeData = [];
    $totalRowsCount = 0;

    foreach ($authorsData as $authorFullName => $author) {
        $authorId = md5($authorFullName);
        $nodeAuthor = new Node(["id" => $authorId,
            "title" => $authorFullName,
            "type" => 2]);
        //$nodeAuthor->children = array_merge($author["seq"],$author["not_seq"] );
        addNodesToArray($author["seq"], $nodeAuthor->children);
        addNodesToArray($author["not_seq"], $nodeAuthor->children);
        $treeData[] = $nodeAuthor;
        $totalRowsCount += count($nodeAuthor->children);

    }


    $treeInfo = [
        'microtime' => microtime(true) - $start,
        'totalRowsCount' => $totalRowsCount,
        'totalBooks' => $booksCount,
        'maxLevel' => 2,
        'treeData' => $treeData
    ];

    return $response->withJson($treeInfo, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
        $cnt = $db->lib_books()
            ->where("bid", $authorRef["bid"])
            ->where("del", null)
            ->where("lang", $lang)
            ->count("*");
        if (intval($cnt) > 0) {
            if (!array_key_exists($authorRef["aid"], $authorRefs)) {
                $authorRefs[$authorRef["aid"]] = [$authorRef["bid"]];
            } else {
                $authorRefs[$authorRef["aid"]][] = $authorRef["bid"];
            }
        }


    }
    $booksTree = [];
    $booksCount = 0;
    foreach ($db->lib_authors()->select("aid, fullname")->where("aid", array_keys($authorRefs))->order("fullname ASC") as $author) {
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
                     ->order("series ASC, serno ASC, title ASC") as $book) {
            $booksCount++;
            if (isset($book["series"]) && strlen($book["series"]) > 0) {
                $serieId = md5($book["series"]);
                $nodeSerie = new Node(["id" => $serieId,
                    "title" => $book["series"],
                    "type" => 3,
                    "level" => 1,
                    "parent" => $author["aid"]]);
                if (!in_array($nodeSerie, $booksTree))
                    $booksTree[] = $nodeSerie;

                $bookInfo = new BookInfo($book);
                $nodeBook = new Node(["id" => $book["bid"],
                    "title" => $book["title"],
                    "type" => 1,
                    "level" => 2,
                    "parent" => $serieId,
                    "bookInfo" => $bookInfo]);
                $booksTree[] = $nodeBook;
            } else {
                $bookInfo = new BookInfo($book);
                $nodeBook = new Node(["id" => $book["bid"],
                    "title" => $book["title"],
                    "type" => 1,
                    "level" => 1,
                    "bookInfo" => $bookInfo]);
                $booksTree[] = $nodeBook;
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
    $start = microtime(true);
    $db = $this->get('settings')['notOrm'];
    $aid = $request->getAttribute('aid');
    $lang = $request->getAttribute('lang');
    $callbackIds = function ($row) {
        return $row["bid"];
    };
    $booksTree = [];
    $booksWithoutSerie = [];
    $localCreateTreeInfo = function ($book) use (&$booksTree, &$booksWithoutSerie) {
        createTreeInfo($book, $booksTree, $booksWithoutSerie);
        return $book["bid"];
    };
    $bookIds = array_map($callbackIds, iterator_to_array($db->lib_author2book()
        ->select("aid, bid")
        ->where("aid", $aid)));

    $allBooks = array_map($localCreateTreeInfo, iterator_to_array($db->lib_books()
        ->select("bid, author, genre, title, series, serno, file, size,  ext, date, lang, path")
        ->where("bid", $bookIds)
        ->where("del", null)
        ->where("lang", $lang)
        ->order("series ASC, serno ASC, title ASC")));

    $treeInfo = [
        'microtime' => microtime(true) - $start,
        'totalIds' => count($bookIds),
        'totalBooks' => count($allBooks),
        'treeData' => []
    ];

    addNodesToArray($booksTree, $treeInfo["treeData"]);
    addNodesToArray($booksWithoutSerie, $treeInfo["treeData"]);
    //array_merge($booksTree , $booksWithoutSerie)
    return $response->withJson($treeInfo, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->run();
