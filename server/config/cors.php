<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // 'uploads/*' added to the default: product/category images are served
    // from the public disk at /uploads/*, and Flutter Web's CanvasKit
    // renderer fetches image bytes via XHR (not a plain <img> tag), which
    // is subject to CORS like any other cross-origin request — without
    // this, Image.network() on web fails with a CORS error even though the
    // same URL loads fine on Android/iOS. Note: when these files are
    // served directly by the webserver as static files (Apache/Nginx in
    // production, or PHP's built-in server under `php artisan serve`),
    // this middleware never runs — see public/uploads/.htaccess for the
    // static-file-serving case.
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'uploads/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
