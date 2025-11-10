<?php

use App\Http\Controllers\Api\EmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\UserController;


Route::prefix('v1')->group(function () {


    Route::post('/auth', [AuthApiController::class, 'auth'])->name('api.auth');

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [AuthApiController::class, 'logout'])->name('api.logout');


        Route::get('/me', function (Request $request) {
            return response()->json([
                'id'        => $request->user()->id,
                'cpf'       => $request->user()->cpf,
                'completed' => $request->user()->completed,
                'name'      => $request->user()->name,
                'email'     => $request->user()->email,
                'phone'     => $request->user()->phone,
            ]);
        });

        Route::apiResource('users', UserController::class);


        Route::post  ('/events',           [EventController::class, 'store' ])->name('api.events.store');
        Route::put   ('/events/{event}',   [EventController::class, 'update'])->name('api.events.update');
        Route::delete('/events/{event}',   [EventController::class, 'destroy'])->name('api.events.destroy');
    });

     Route::get('/events', [EventController::class, 'index'])->name('api.events.index');
     Route::get('/events/{event}', [EventController::class, 'show'])->name('api.events.show');
});

Route::prefix('v1')->group(function () {
    Route::post('/emails', [EmailController::class, 'store'])->name('api.emails.store');
});
