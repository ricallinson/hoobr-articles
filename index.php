<?php
namespace php_require\hoobr_post;

$pathlib = $require("php-path");
$keyval = $require("php-keyval");
$uuid = $require("php-uuid");
$render = $require("php-render-php");
$req = $require("php-http/request");
$res = $require("php-http/response");

$store = $keyval($pathlib->join($req->cfg("datroot"), "posts"), 10);

function getFirstPostId($store) {
    $keys = $store->getKeys(0, 1);
    if (count($keys) <= 0) {
        return null;
    }
    return $keys[0];
}

function getPostsList($store, $from=0, $to=null) {

    $posts = array();
    $postIds = $store->getKeys($from, $to);

    foreach ($postIds as $postId) {
        $posts[$postId] = $store->get($postId)["title"];
    }

    return $posts;
}

/*
    List all posts in a menu.
*/

$exports["menu"] = function () use ($req, $render, $store, $pathlib) {

    $postId = $req->param("post-id");
    $posts = getPostsList($store);

    if (!$postId) {
        reset($posts);
        $postId = key($posts);
    }

    return $render($pathlib->join(__DIR__, "views", "menu.php.html"), array(
        "posts" => $posts,
        "current" => $postId
    ));
};

/*
    Show a post.
*/

$exports["main"] = function () use ($req, $render, $store, $pathlib) {

    $postId = $req->param("post-id");

    if (!$postId) {
        // if there is no postId get the first one returned by store?
        $postId = getFirstPostId($store);
    }

    $post = $store->get($postId);

    return $render($pathlib->join(__DIR__, "views", "main.php.html"), array(
        "post" => $post
    ));
};

/*
    List all posts in a sidebar.
*/

$exports["admin-sidebar"] = function () use ($req, $render, $store, $pathlib) {

    $postId = $req->param("post-id");
    $posts = getPostsList($store);

    return $render($pathlib->join(__DIR__, "views", "admin-sidebar.php.html"), array(
        "posts" => $posts,
        "current" => $postId
    ));
};

/*
    This is not good. Needs work.

    CRUD Create, Read, Update, Delete
*/

$exports["admin-main"] = function () use ($req, $res, $render, $store, $pathlib, $uuid) {

    $action = strtolower($req->param("hoobr-post-action"));
    $saved = false;
    $postId = $req->param("post-id");
    $title = $req->param("title");
    $text = $req->param("text");

    if ($action === "delete" && $postId) {

        $store->delete($postId);

        $res->redirect("?module=hoobr-posts&action=main");

    } else if ($action === "save page" && $postId) {

        if (!$title) {
            $title = "New Post";
        }

        // save the post
        $saved = $store->put($postId, array("title" => $title, "text" => $text));

    } else if ($action === "new" || !$postId) {

        // starting a new post
        $postId = $uuid->generate(1, 101);

    }

    // load the post
    $post = $store->get($postId);
    $title = $post["title"];
    $text = $post["text"];

    return $render($pathlib->join(__DIR__, "views", "admin-main.php.html"), array(
        "postId" => $postId,
        "title" => $title,
        "text" => $text,
        "saved" => $saved ? "Saved" : ""
    ));
};
