<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API docs (OpenAPI/Swagger, via Scramble) at /docs. The package's own
// default route (/docs/api) is suppressed in AppServiceProvider::register().
Scramble::registerUiRoute(path: 'docs')->name('scramble.docs.ui');
Scramble::registerJsonSpecificationRoute(path: 'docs/openapi.json')->name('scramble.docs.document');
