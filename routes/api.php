<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Http\Controllers\Api\EventController;

Route::prefix('v1')->group(function () {
    // Se quiser proteger com Sanctum depois, adicione ->middleware('auth:sanctum')
    Route::get('/events',        [EventController::class, 'index'])->name('api.events.index');
    Route::post('/events',       [EventController::class, 'store'])->name('api.events.store');
    Route::get('/events/{event}',[EventController::class, 'show'])->name('api.events.show');
    Route::put('/events/{event}',[EventController::class, 'update'])->name('api.events.update');
    Route::delete('/events/{event}',[EventController::class, 'destroy'])->name('api.events.destroy');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
