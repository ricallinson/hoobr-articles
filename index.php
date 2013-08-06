<?php
namespace php_require\hoobr_articles;

$pathlib = $require("php-path");
$ContentStore = $require("hoobr-content-store");
$uuid = $require("php-uuid");
$render = $require("php-render-php");
$req = $require("php-http/request");
$res = $require("php-http/response");

$store = $ContentStore("articles", 10 /*seconds*/);

function getFirstArticleId($store) {
    $keys = $store->getKeys(0, 1);
    if (count($keys) <= 0) {
        return null;
    }
    return $keys[0];
}

function getArticlesList($store, $from=0, $length=null) {

    $articles = array();
    $articleIds = $store->getKeys($from, $length);

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
    Render a single article form the given "article-id".

    $params = array(
        "article-id" => UUID,
        "view" => "article" | "splash"
    )
*/

$exports["article"] = function ($params) use ($req, $render, $store, $pathlib) {

    $articleId = isset($params["article-id"]) ? $params["article-id"] : null;

    if (!$articleId) {
        // if there is no articleId return nothing.
        return "";
    }

    $article = $store->get($articleId);

    if (!$article) {
        // if there is no article found return nothing.
        return "";
    }

    $view = isset($params["view"]) ? $params["view"] : "article";

    return $render($pathlib->join(__DIR__, "views", $view . ".php.html"), array(
        "article" => $article
    ));
};

/*
    Render 1 or more articles with pagination or endless scroll.

    $params = array(
        "start" => int,
        "length" => int,
        "more" => "scroll" | "page",
        "category" => ""
    )
*/

$exports["main"] = function ($params) use ($req, $render, $store, $pathlib) {

    $from = isset($params["from"]) ? $params["from"] : 0;
    $length = isset($params["length"]) ? $params["length"] : 10;
    $more = isset($params["more"]) ? $params["more"] : "scroll";

    $articles = array();
    $articleIds = $store->getKeys($from, $length);

    foreach ($articleIds as $articleId) {
        $articles[$articleId] = $store->get($articleId);
    }

    return $render($pathlib->join(__DIR__, "views", "main.php.html"), array(
        "articles" => $articles
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

    $action = strtolower($req->param("hoobr-articles-action"));
    $saved = false;
    $articleId = $req->param("article-id");
    $title = $req->param("title");
    $text = $req->param("text");

    if ($action === "delete" && $articleId) {

        $store->delete($articleId);

        $res->redirect("?page=admin&module=hoobr-articles&action=main");

    } else if ($action === "save" && $articleId) {

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
