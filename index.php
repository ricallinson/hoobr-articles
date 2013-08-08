<?php
namespace php_require\hoobr_articles;

$pathlib = $require("php-path");
$ContentStore = $require("hoobr-content-store");
$render = $require("php-render-php");
$req = $require("php-http/request");
$res = $require("php-http/response");
$markdown = $require("./lib/markdown");

$store = $ContentStore("articles", 10 /*seconds*/);

/*
    Generate an ID.
*/

function genId() {
    return round(microtime(true)) . "-" . uniqid(true);
}

/*
    Get the ID for the first article found.
*/

function getFirstArticleId($store, $filters = array()) {
    $keys = $store->getKeys(0, 1, $filters);
    if (count($keys) <= 0) {
        return null;
    }
    return $keys[0];
}

/*
    Get a list of article ID's in creation order.
*/

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

    $params = array(
        "article-id" => UUID,
        "category" => String
    )
*/

$exports["menu"] = function () use ($req, $render, $store, $pathlib) {

    $articleId = $req->param("article-id");
    $category = $req->param("category", $params);
    $filters = $category ? array("category" => $params["category"]) : null;

    $articles = getArticlesList($store, 0, null, $filters);

    return $render($pathlib->join(__DIR__, "views", "sidebar.php.html"), array(
        "articles" => $articles,
        "current" => $articleId
    ));
};

/*
    List all articles in a sidebar.

    $params = array(
        "article-id" => UUID,
        "title" => String,
        "category" => String
    )
*/

$exports["sidebar"] = function ($params) use ($req, $render, $store, $pathlib) {

    $articleId = $req->param("article-id");
    $title = $req->find("title", $params, "Articles");
    $category = $req->find("category", $params);

    $filters = $category ? array("category" => $category) : null;
    $articles = getArticlesList($store, 0, null, $filters);

    return $render($pathlib->join(__DIR__, "views", "sidebar.php.html"), array(
        "articles" => $articles,
        "current" => $articleId,
        "title" => $title,
        "category" => $category
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

    $articleId = $req->find("article-id", $params, $req->param("article-id"));

    if (!$articleId) {
        // if there is no articleId return not found.
        return $render($pathlib->join(__DIR__, "views", "not-found.php.html"));
    }

    $article = $store->get($articleId);

    if (!$article) {
        // if there is no article found return nothing.
        return $render($pathlib->join(__DIR__, "views", "not-found.php.html"));
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
        "category" => string
    )
*/

$exports["main"] = function ($params) use ($req, $render, $store, $pathlib, $markdown) {

    $from = $req->param("from", 0);
    $length = $req->param("length", 3);
    $category = $req->param("category", $params);
    $articles = array();

    /*
        Create a filter and get the ID's to render.
    */

    $filters = $category ? array("category" => $category) : null;
    $articleIds = $store->getKeys($from, $length, $filters);

    $total = count($articleIds);

    /*
        Get each article and transform their text using markdown.
    */

    foreach ($articleIds as $articleId) {
        $articles[$articleId] = $store->get($articleId);
        $articles[$articleId]["text"] = $markdown($articles[$articleId]["text"]);
    }

    /*
        Render the article. 
    */

    if ($total > 0) {
        $articlesRender = $render($pathlib->join(__DIR__, "views", "article.php.html"), array(
            "articles" => $articles
        ));
    } else {
        // if there is no article found return nothing.
        $articlesRender = $render($pathlib->join(__DIR__, "views", "end.php.html"));
    }

    /*
        Render the paging links.
    */

    $pagingRender = $render($pathlib->join(__DIR__, "views", "paging.php.html"), array(
        "category" => $category,
        "previous" => $from - $length,
        "next" => $total === $length ? $from + $length : 0
    ));

    /*
        Return the rendered views.
    */

    return $articlesRender . $pagingRender;
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

$exports["admin-main"] = function () use ($req, $res, $render, $store, $pathlib) {

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
        $articleId = genId();

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
