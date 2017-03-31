<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Books\BookInfo as BookInfo;

include $_SERVER['DOCUMENT_ROOT'] . '/v1/slim.app.php';
include $_SERVER['DOCUMENT_ROOT'] . '/modules/BookInfo.php';

$app->get('/series/search/[{q}]', function (Request $request, Response $response) {

    $db = $this->get('settings')['notOrm'];
    $q = $request->getAttribute('q');
    $series = [];
    if (isset($q)) {
        foreach ($db->lib_books()->select("DISTINCT SERIES")->where("SERIES LIKE ?", "%$q%")->order("SERIES")->limit(50) as $serie) {
            array_push($series, $serie["SERIES"]);
        }
    }
    return $response->withJson($series, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->get('/languages', function (Request $request, Response $response) {

    $db = $this->get('settings')['notOrm'];
    $languages = [];
    foreach ($db->lib_books()->select("DISTINCT lang")->order("lang") as $lang) {
        array_push($languages, $lang["lang"]);
    }
    return $response->withJson($languages, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});
$app->get('/genres/search/[{q}]', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $q = $request->getAttribute('q');
    if (isset($q)) {
        $cursor = $db->lib_genres()->select("code, gdesc, edesc")->where("gdesc LIKE ?", "%$q%")->or("edesc LIKE ?", "%$q%")->limit(50);

    } else {
        $cursor = $db->lib_genres()->select("code, gdesc, edesc");
    }
    $genres = array_map(function ($row) {
        return array("code" => $row["code"], "gdesc" => $row["gdesc"], "edesc" => $row["edesc"]);
    }, iterator_to_array($cursor));
    return $response->withJson($genres, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});
$app->get('/authors/search/[{q}]', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $q = $request->getAttribute('q');
    $authors = [];
    if (isset($q)) {
        $cursor = $db->lib_books_view()->select("AUTHOR, AUTHOR_DISPLAY")->where("AUTHOR_DISPLAY LIKE ?", "%$q%")->limit(50);
        foreach ($cursor as $author) {
            $names = explode(":", $author["AUTHOR"]);
            foreach ($names as $name) {
                if (strlen($name)) {
                    $displayName = str_replace(",", " ", $name);
                    if (stripos(mb_strtolower($displayName), $q) !== false) {
                        $authors[$name] = $displayName;
                    }
                }
            }
        }
        asort($authors);
    }
    return $response->withJson($authors, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->post('/books/search', function (Request $request, Response $response) {
    $db = $this->get('settings')['notOrm'];
    $body = $request->getParsedBody();
    $prepare_genres_query = function($genres = array()){
        $arr = array_map(function($genre){
            $code = $genre["code"];
            return "GENRE LIKE '%$code%'";
        }, $genres);
        $query = join(" OR ", $arr);
        return $query;
    };
    $cursor = $db->lib_books_view()
        ->select("AUTHOR, GENRE, TITLE, SERIES, SERNO,FILE, SIZE, LIBID, DEL, EXT, DATE, LANG, LIBRATE, KEYWORDS, PATH")
        ->where("LANG", $body["language"])
        ->and("del", null);
    if (isset($body["genres"])) {
        $query = $prepare_genres_query($body["genres"]);
        $cursor = $cursor->and($query);
    }
    $query_arr = array();
    if (!empty($body["bookTitle"]) && !is_null("bookTitle")) {
        $title = $body["bookTitle"];
        $query_arr[] = "TITLE LIKE '%$title%'";
    }
    if (isset($body["author"])) {
        $author = $body["author"]["key"];
        $query_arr[] = "AUTHOR LIKE '%$author%'";
    }
    if (!empty($body["serie"]) && !is_null("serie")) {
        $serie = $body["serie"];
        $query_arr[] = "SERIES LIKE '%$serie%'";
    }
    if (isset($body["operator"]) && $body["operator"]) {
        $query = join(" AND ", $query_arr);
    } else {
        $query = join(" OR ", $query_arr);
    }
    if(!empty($query)){
        $cursor = $cursor->and($query);
    }
    $cursor = $cursor->order("SERIES ASC, SERNO ASC");
    if($cursor->count()>1000) $cursor = $cursor->limit(1000);
    $books = array_map(function ($row) {
        return new BookInfo($row);
    }, iterator_to_array($cursor));
    return $response->withJson($books, 201, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->run();
