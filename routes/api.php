<?php

use App\Http\Controllers\Api\CertificateProxyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventRegistrationController;
use App\Http\Controllers\Api\EmailController;

Route::prefix('v1')->group(function () {

    Route::post('/users', [UserController::class, 'store'])
        ->name('api.users.store');
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
        })->name('api.me');

        Route::apiResource('users', UserController::class)->except(['store']);


        Route::post('/events',          [EventController::class, 'store' ])->name('api.events.store');
        Route::put('/events/{event}',  [EventController::class, 'update'])->name('api.events.update');
        Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('api.events.destroy');

        Route::get('/my-registrations', [EventRegistrationController::class, 'myRegistrations'])
            ->name('api.events.my-registrations');

        Route::delete('/events/{event}/register', [EventRegistrationController::class, 'unregister'])
            ->name('api.events.unregister');

        Route::post('/events/{event}/presence', [EventRegistrationController::class, 'markPresence'])
            ->name('api.events.presence');

        Route::post('/certificates', [CertificateProxyController::class, 'emit'])
            ->name('api.certificates.emit');

        Route::get('/certificates/{id}', [CertificateProxyController::class, 'show'])
            ->name('api.certificates.show');
    });

    Route::middleware('auth:sanctum')->get(
        '/events/{event}/certificate',
        [CertificateProxyController::class, 'myForEvent']
    );

    Route::post('/events/{event}/register', [EventRegistrationController::class, 'register'])
        ->name('api.events.register');

    Route::get('/certificates/verify/{code}', [CertificateProxyController::class, 'verify'])
        ->name('api.certificates.verify');


    Route::get('/events',       [EventController::class, 'index'])->name('api.events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('api.events.show');


    Route::post('/emails', [EmailController::class, 'store'])->name('api.emails.store');

    Route::middleware('sync.api')->group(function () {
        Route::post('/sync/full', [SyncController::class, 'fullSync']);
    });
});
