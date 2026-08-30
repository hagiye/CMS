<?php

use App\Http\Controllers\Api\V1\AuthController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

use App\Http\Controllers\Api\V1\ContentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // public
    Route::get('/nodes', [ContentController::class, 'index']);
    Route::get('/nodes/{slug}', [ContentController::class, 'show']);
    Route::get('/search', [ContentController::class, 'search'])->middleware('throttle:search');
    Route::get('/nodes/{slug}/links', [ContentController::class, 'links']);
    Route::get('/nodes/{slug}/documents', [ContentController::class, 'documents']);

    // auth (mobile)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/bookmarks', [ContentController::class, 'bookmark'])->middleware('abilities:bookmarks:write');
        Route::get('/bookmarks', [ContentController::class, 'myBookmarks'])->middleware('abilities:bookmarks:read');
        Route::delete('/bookmarks/{contentNode}', [ContentController::class, 'destroyBookmark'])->middleware('abilities:bookmarks:write');
    });
});
