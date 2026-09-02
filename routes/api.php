<?php

use Illuminate\Support\Facades\Route;
use Modules\MeiliFacets\Http\Controllers\MeiliFacetsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('meilifacets', MeiliFacetsController::class)->names('meilifacets');
});
