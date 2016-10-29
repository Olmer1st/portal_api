<?php

require '../../vendor/autoload.php';
include '../../middleware/connection.php';



#a-автор, t-переводчик, i-иллюстратор, s-составитель, r-редактор
$app = new \Slim\Slim();
$app->contentType('text/html; charset=utf-8');

$app->get('/library/all/:limit', function ($limit) use($app) {
    $db = connect();
    $authors = $db->libavtors()->select("aid, FirstName, middlename, LastName")->limit($limit);
    $app->response()->header('Content-Type', 'application/json', 'charset=utf8');
    echo json_encode($authors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->get('/library/:q/:search(/:limit)', function ($q, $search, $limit = 10) use($app) {
    $db = connect();

    $authors = $db->authors()->select("aid, FullName")->where("$q LIKE ?", "%$search%")->limit($limit);
    $app->response()->header('Content-Type', 'application/json', 'charset=utf8');
    echo json_encode($authors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->get('/books/:limit', function ($limit) {
    $connection = new PDO("mysql:dbname=library;host=127.0.0.1;charset=utf8", "library_admin", "123456");
    $db = new NotORM($connection);
    $books = $db->libbook()->select("bid, Title, Title1")->limit($limit);
    foreach ($books as $id => $book) {
        echo "$book[Title] $book[Title1]<br>";
    }
});

$app->get('/books/by/:aid(/:lang)(/:deleted)', function ($aid, $lang = NULL, $deleted = NULL) use($app) {
    $connection = new PDO("mysql:dbname=library;host=127.0.0.1;charset=utf8", "library_admin", "123456");
    $db = new NotORM($connection);
    $booksBySeries = [];
    $books = $db->BooksByAuthor()->where("aid = ?", $aid);
    if ($deleted) {
        $books = $books->where("Deleted <> ?", 1);
    }
    if ($lang) {
        $books = $books->where("lang = ?", $lang);
    }
    $booksBySeries["count"] =count($books);
    $noseq =[];
    $seq = [];
    /* @var $book type */
    foreach ($books as $book) {
        $series = $db->BooksBySerie()->where("bid = ?", $book["bid"]);
        if(count($series)>0) {

            foreach ($series as $serie) {
                $book["sn"] = $serie["sn"];
                if (array_key_exists($serie["sid"], $seq)) {
                    array_push($seq[$serie["sid"]]["books"], $book);
                } else {
                    $seq[$serie["sid"]]["name"]= $serie["seqname"];
                    $seq[$serie["sid"]]["books"]= [$book];
                }
            }
        }
        else {
            $noseq[$book["bid"]] = $book;
        }
    }

    $booksBySeries["noseq"] = $noseq;
    $booksBySeries["seq"] = $seq;
    $app->response()->header('Content-Type', 'application/json', 'charset=utf8');
    echo json_encode($booksBySeries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

$app->run();

?>
