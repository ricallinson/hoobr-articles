<?php
namespace php_require\hoobr_articles;

$pathlib = $require("php-path");
$keyval = $require("php-keyval");
$uuid = $require("php-uuid");
$render = $require("php-render-php");
$req = $require("php-http/request");
$res = $require("php-http/response");

$store = $keyval($pathlib->join($req->cfg("datroot"), "articles"), 10);

function getFirstArticleId($store) {
    $keys = $store->getKeys(0, 1);
    if (count($keys) <= 0) {
        return null;
    }
    return $keys[0];
}

function getArticlesList($store, $from=0, $to=null) {

    $articles = array();
    $articleIds = $store->getKeys($from, $to);

    foreach ($articleIds as $articleId) {
        $articles[$articleId] = $store->get($articleId)["title"];
    }

    return $articles;
}

/*
    List all articles in a menu.
*/

$exports["menu"] = function () use ($req, $render, $store, $pathlib) {

    $articleId = $req->param("article-id");
    $articles = getArticlesList($store);

    if (!$articleId) {
        reset($articles);
        $articleId = key($articles);
    }

    return $render($pathlib->join(__DIR__, "views", "menu.php.html"), array(
        "articles" => $articles,
        "current" => $articleId
    ));
};

/*
    Show a article.
*/

$exports["main"] = function () use ($req, $render, $store, $pathlib) {

    $articleId = $req->param("article-id");

    if (!$articleId) {
        // if there is no articleId get the first one returned by store?
        $articleId = getFirstArticleId($store);
    }

    $article = $store->get($articleId);

    return $render($pathlib->join(__DIR__, "views", "main.php.html"), array(
        "article" => $article
    ));
};

/*
    List all articles in a sidebar.
*/

$exports["admin-sidebar"] = function () use ($req, $render, $store, $pathlib) {

    $articleId = $req->param("article-id");
    $articles = getArticlesList($store);

    return $render($pathlib->join(__DIR__, "views", "admin-sidebar.php.html"), array(
        "articles" => $articles,
        "current" => $articleId
    ));
};

/*
    This is not good. Needs work.

    CRUD Create, Read, Update, Delete
*/

$exports["admin-main"] = function () use ($req, $res, $render, $store, $pathlib, $uuid) {

    $action = strtolower($req->param("hoobr-article-action"));
    $saved = false;
    $articleId = $req->param("article-id");
    $title = $req->param("title");
    $text = $req->param("text");

    if ($action === "delete" && $articleId) {

        $store->delete($articleId);

        $res->redirect("?module=hoobr-articles&action=main");

    } else if ($action === "save page" && $articleId) {

        if (!$title) {
            $title = "New Article";
        }

        // save the article
        $saved = $store->put($articleId, array("title" => $title, "text" => $text));

    } else if ($action === "new" || !$articleId) {

        // starting a new article
        $articleId = $uuid->generate(1, 101);

    }

    // load the article
    $article = $store->get($articleId);
    $title = $article["title"];
    $text = $article["text"];

    return $render($pathlib->join(__DIR__, "views", "admin-main.php.html"), array(
        "articleId" => $articleId,
        "title" => $title,
        "text" => $text,
        "saved" => $saved ? "Saved" : ""
    ));
};
