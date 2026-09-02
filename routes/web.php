<?php

use Illuminate\Support\Facades\Route;
use Modules\MeiliFacets\Http\Controllers\MeiliFacetsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('meilifacets', MeiliFacetsController::class)->names('meilifacets');
});
