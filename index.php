<?php
namespace php_require\hoobr_articles;

$pathlib = $require("php-path");
$ContentStore = $require("hoobr-content-store");
$uuid = $require("php-uuid");
$render = $require("php-render-php");
$req = $require("php-http/request");
$res = $require("php-http/response");
$markdown = $require("./lib/markdown");

$store = $ContentStore("articles", 10 /*seconds*/);

function getFirstArticleId($store) {
    $keys = $store->getKeys(0, 1);
    if (count($keys) <= 0) {
        return null;
    }
    return $keys[0];
}

function getArticlesList($store, $from = 0, $length = null, $filters = array()) {

    $articles = array();
    $articleIds = $store->getKeys($from, $length, $filters);

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
    $category = isset($params["category"]) ? $params["category"] : $req->param("category");
    $category = $category ? array("category" => $params["category"]) : null;

    $articles = getArticlesList($store, 0, null, $category);

    return $render($pathlib->join(__DIR__, "views", "sidebar.php.html"), array(
        "articles" => $articles,
        "current" => $articleId
    ));
};

/*
    List all articles in a menu.
*/

$exports["sidebar"] = function ($params) use ($req, $render, $store, $pathlib) {

    $articleId = $req->param("article-id");
    $category = isset($params["category"]) ? $params["category"] : $req->param("category");
    $category = $category ? array("category" => $params["category"]) : null;

    $articles = getArticlesList($store, 0, null, $category);

    return $render($pathlib->join(__DIR__, "views", "sidebar.php.html"), array(
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

$exports["article"] = function ($params) use ($req, $render, $store, $pathlib, $markdown) {

    $articleId = isset($params["article-id"]) ? $params["article-id"] : null;
    $articleId = $articleId ? $articleId : $req->param("article-id");

    if (!$articleId) {
        // if there is no articleId return not found.
        return "Not found."; // should be a template
    }

    $article = $store->get($articleId);

    if (!$article) {
        // if there is no article found return nothing.
        return "Not found."; // should be a template
    }

    $view = isset($params["view"]) ? $params["view"] : "article";

    $article["text"] = $markdown($article["text"]);

    return $render($pathlib->join(__DIR__, "views", $view . ".php.html"), array(
        "articles" => array($article)
    ));
};

/*
    Render 1 or more articles with pagination or endless scroll.

    $params = array(
        "start" => int,
        "length" => int,
        "category" => "",
        "more" => "scroll" | "page"
    )
*/

$exports["main"] = function ($params) use ($req, $render, $store, $pathlib, $markdown) {

    $from = isset($params["from"]) ? $params["from"] : 0;
    $length = isset($params["length"]) ? $params["length"] : 10;
    $category = isset($params["category"]) ? $params["category"] : $req->param("category");
    $category = $category ? array("category" => $params["category"]) : null;
    $more = isset($params["more"]) ? $params["more"] : "scroll";

    $articles = array();
    $articleIds = $store->getKeys($from, $length, $category);

    foreach ($articleIds as $articleId) {
        $articles[$articleId] = $store->get($articleId);
        $articles[$articleId]["text"] = $markdown($articles[$articleId]["text"]);
    }

    return $render($pathlib->join(__DIR__, "views", "article.php.html"), array(
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

    // Get the article from form values.
    $articleId = $req->param("article-id");
    $title = $req->param("title");
    $category = $req->param("category");
    $text = $req->param("text");

    if ($action === "delete" && $articleId) {

        $store->delete($articleId);

        $res->redirect("?page=admin&module=hoobr-articles&action=main");

    } else if ($action === "save" && $articleId) {

        if (!$title) {
            $title = "New Article";
        }

        // Save the article.
        $saved = $store->put($articleId, array("title" => $title, "category" => $category, "text" => $text));

    } else if ($action === "new" || !$articleId) {

        // Starting a new article.
        $articleId = $uuid();

    }

    // Load the article.
    $article = $store->get($articleId);
    $title = $article["title"];
    $category = $article["category"];
    $text = $article["text"];

    // Render the form.
    return $render($pathlib->join(__DIR__, "views", "admin-main.php.html"), array(
        "articleId" => $articleId,
        "title" => $title,
        "category" => $category,
        "text" => $text,
        "saved" => $saved ? "Saved" : ""
    ));
};

/*
    Testing ideas for module level assets.
*/

$exports["config"] = array(
    "js" => array(
        "bottom" => array(
            "./node_modules/hoobr-articles/assets/js/articles.js"
        )
    )
);
