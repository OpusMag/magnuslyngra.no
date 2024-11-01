<?php
$request = $_SERVER['REQUEST_URI'];
switch ($request) {
    case '/' :
        require __DIR__ . '/index.html';
        break;
    case '/ommeg' :
        require __DIR__ . '/ommeg.html';
        break;
    case '/cv' :
        require __DIR__ . '/cv.html';
        break;
    case '/kontakt' :
        require __DIR__ . '/kontakt.html';
        break;
    case '/prosjekter' :
        require __DIR__ . '/prosjekter.html';
        break;
    case '/blogg' :
        require __DIR__ . '/blogg.html';
        break;
    default:
        http_response_code(404);
        require __DIR__ . '/404.html';
        break;
}